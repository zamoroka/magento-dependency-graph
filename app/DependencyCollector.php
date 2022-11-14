<?php

namespace Zamoroka\MagentoDependencyGraph;

use Roave\BetterReflection\BetterReflection;
use Roave\BetterReflection\Reflection\ReflectionClass;
use Roave\BetterReflection\Reflector\DefaultReflector;
use Roave\BetterReflection\SourceLocator\Type\ComposerSourceLocator;

class DependencyCollector
{
    /** @var string */
    private $projectDir;
    /** @var string|null */
    private $moduleVendor;
    /** @var ClassFinder */
    private $classFinder;
    /** @var array */
    private $dependencies = [];
    /** @var array */
    private $skipped = [];
    /** @var array[] */
    private $modulesByLocation = [
        'app_code' => [],
        'vendor' => [],
    ];

    public function __construct(string $projectDir, string $moduleVendor = null)
    {
        $this->projectDir = $projectDir;
        $this->moduleVendor = $moduleVendor;
        $this->classFinder = new ClassFinder();
    }

    /**
     * @return array
     */
    public function getDependencies()
    {
        $dependenciesAppCode = $this->getDependenciesForDir('app/code/' . $this->moduleVendor);
        $dependenciesVendor = $this->getDependenciesForDir('vendor/' . strtolower($this->moduleVendor));
        $this->modulesByLocation['app_code'] = array_keys($dependenciesAppCode);
        $this->modulesByLocation['vendor'] = array_keys($dependenciesVendor);
        $this->dependencies = array_merge_recursive($this->dependencies, $dependenciesAppCode, $dependenciesVendor);
        ksort($this->dependencies);

        return $this->dependencies;
    }

    /**
     * Check if module is independent
     *
     * @param $item
     * @param $dependencies
     *
     * @return bool
     */
    public function isIndependent($item, $dependencies)
    {
        foreach ($dependencies as $dependency) {
            if (in_array($item, $dependency)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array
     */
    public function collectAllModules()
    {
        $all = [];
        foreach ($this->dependencies as $key => $dependencies) {
            $all[] = $key;
            foreach ($dependencies as $dependency) {
                $all[] = $dependency;
            }
        }

        return array_unique($all);
    }

    /**
     * @param $moduleName
     *
     * @return bool
     */
    public function isInVendor($moduleName)
    {
        if (in_array($moduleName, $this->modulesByLocation['vendor'])) {
            return true;
        }

        return false;
    }

    /**
     * @param $moduleName
     *
     * @return bool
     */
    public function isInAppCode($moduleName)
    {
        if (in_array($moduleName, $this->modulesByLocation['app_code'])) {
            return true;
        }

        return false;
    }

    /**
     * @param string $dir
     *
     * @return array
     */
    private function getDependenciesForDir(string $dir)
    {
        $dir = $this->projectDir . $dir;
        $collect = [];
        $classLoader = require $this->projectDir . 'vendor/autoload.php';
        $astLocator = (new BetterReflection())->astLocator();
        $reflector = new DefaultReflector(new ComposerSourceLocator($classLoader, $astLocator));
        foreach ($this->classFinder->getAllClasses($dir) as $class) {
            try {
                $classInfo = $reflector->reflectClass($class['class_name']);
                //        --- current module ---
                $currentModule = $this->getModuleName($classInfo);
                if (!$currentModule) {
                    continue;
                }
                if (!isset($collect[$currentModule])) {
                    $collect[$currentModule] = [];
                }
                //        --- parent module ---
                $parentModule = $this->getModuleName($classInfo->getParentClass());
                if ($parentModule && $parentModule != $currentModule) {
                    $collect[$currentModule][] = $parentModule;
                    $this->addModuleNode($parentModule);
                }
                //        --- constructor dependencies ---
                foreach ($this->getConstructorDependencies($classInfo, $currentModule) as $constructorDependency) {
                    $collect[$currentModule][] = $constructorDependency;
                    $this->addModuleNode($constructorDependency);
                }
                //        --- interface dependencies ---
                foreach ($this->getInterfaceDependencies($classInfo, $currentModule) as $interfaceDependency) {
                    $collect[$currentModule][] = $interfaceDependency;
                    $this->addModuleNode($interfaceDependency);
                }
                //        --- unique dependencies ---
                $collect[$currentModule] = array_unique($collect[$currentModule]);
            } catch (\Exception $exception) {
                $this->skipped[] = $class;
            }
        }
        //        --- module.xml dependencies ---
        $moduleXml = $this->classFinder->rSearch($dir, "/.*module\.xml/");
        foreach ($moduleXml as $filename) {
            $xml = file_get_contents($filename);
            $dom = new \DOMDocument();
            $dom->loadXML($xml);
            $moduleNode = $dom->getElementsByTagName('config')->item(0)->getElementsByTagName('module')->item(0);
            $moduleName = $moduleNode->getAttribute('name');
            if ($moduleName && !isset($collect[$moduleName])) {
                $collect[$moduleName] = [];
            }
            $dependList = $moduleNode->getElementsByTagName('sequence');
            if ($dependList->length > 0) {
                $dependencies = $dependList->item(0)->getElementsByTagname('module');
                foreach ($dependencies as $dependency) {

                    /** @var $dependency string */
                    $dependsOn = $dependency->getAttribute('name');
                    $collect[$moduleName][] = $dependsOn;
                    $this->addModuleNode($dependsOn);
                }
            }
            $collect[$moduleName] = array_unique($collect[$moduleName]);
        }

        return $collect;
    }

    /**
     * Create node is needed for all modules even if module not exists
     *
     * @param $module
     *
     * @return DependencyCollector
     */
    private function addModuleNode($module)
    {
        if ($this->moduleVendor && strpos($module, $this->moduleVendor) === false) {
            return $this;
        }
        if (!isset($this->dependencies[$module])) {
            $this->dependencies[$module] = [];
        }

        return $this;
    }

    /**
     * @param ReflectionClass $classInfo
     *
     * @param string $currentModule
     *
     * @return array
     */
    private function getConstructorDependencies(ReflectionClass $classInfo, string $currentModule)
    {
        $modules = [];
        try {
            $parameters = $classInfo->getConstructor()->getParameters();
            foreach ($parameters as $parameter) {
                $constructorDependency = $this->getModuleName($parameter->getClass());
                if ($constructorDependency && $constructorDependency != $currentModule) {
                    $modules[] = $constructorDependency;
                }
            }
        } catch (\OutOfBoundsException $exception) {
            // skip if there is no constructor
        }

        return $modules;
    }

    /**
     * @param ReflectionClass $classInfo
     *
     * @param string $currentModule
     *
     * @return array
     */
    private function getInterfaceDependencies(ReflectionClass $classInfo, string $currentModule)
    {
        $modules = [];
        foreach ($classInfo->getInterfaces() as $interface) {
            $interfaceDependency = $this->getModuleName($interface);
            if ($interfaceDependency && $interfaceDependency != $currentModule) {
                $modules[] = $interfaceDependency;
            }
        }

        return $modules;
    }

    /**
     * @param ReflectionClass|null $class
     *
     * @return string|null
     */
    private function getModuleName(?ReflectionClass $class)
    {
        if (!$class || !$class->getName()) {
            return null;
        }
        $data = explode('\\', $class->getName());
        if (!isset($data[0]) || !isset($data[1])) {
            return null;
        }

        return $data[0] . "_" . $data[1];
    }
}
