<?php

namespace Zamoroka\MagentoDependencyGraph;

use Roave\BetterReflection\BetterReflection;
use Roave\BetterReflection\Reflection\ReflectionClass;
use Roave\BetterReflection\Reflector\ClassReflector;
use Roave\BetterReflection\SourceLocator\Type\ComposerSourceLocator;

class DependencyCollector
{
    /** @var string */
    private $projectDir;
    /** @var array */
    private $dependencies = [];
    /** @var array[] */
    private $modulesByLocation = [
        'app_code' => [],
        'vendor' => [],
    ];
    private $foldersToSkip = [
        'Test',
        'Tests',
        'tests',
        'Sniffs',
        'PHP_CodeSniffer',
        'Unit',
        'MagentoHackathon',
    ];
    private $skipped = [];
    /** @var string|null */
    private $moduleVendor;

    public function __construct(string $projectDir, string $moduleVendor = null)
    {
        $this->projectDir = $projectDir;
        $this->moduleVendor = $moduleVendor;
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
        $this->dependencies = $dependenciesAppCode + $dependenciesVendor;
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

    public function getClassFullNameFromFile($filePathName)
    {
        $className = $this->getClassNameFromFile($filePathName);
        if (!$className) {
            throw new \Exception("not a class");
        }

        return $this->getClassNamespaceFromFile($filePathName) . '\\' . $this->getClassNameFromFile($filePathName);
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
        if (!in_array($moduleName, $this->modulesByLocation['app_code'])) {
            return true;
        }

        return false;
    }

    /**
     * @param $filePathName
     *
     * @return string|null
     */
    private function getClassNamespaceFromFile($filePathName)
    {
        $src = file_get_contents($filePathName);
        $tokens = token_get_all($src);
        $count = count($tokens);
        $i = 0;
        $namespace = '';
        $namespace_ok = false;
        while ($i < $count) {
            $token = $tokens[$i];
            if (is_array($token) && $token[0] === T_NAMESPACE) {
                // Found namespace declaration
                while (++$i < $count) {
                    if ($tokens[$i] === ';') {
                        $namespace_ok = true;
                        $namespace = trim($namespace);
                        break;
                    }
                    $namespace .= is_array($tokens[$i]) ? $tokens[$i][1] : $tokens[$i];
                }
                break;
            }
            $i++;
        }
        if (!$namespace_ok) {
            return null;
        } else {
            return $namespace;
        }
    }

    /**
     * @param $filePathName
     *
     * @return mixed|null
     */
    private function getClassNameFromFile($filePathName)
    {
        $php_code = file_get_contents($filePathName);
        $classes = array();
        $tokens = token_get_all($php_code);
        $count = count($tokens);
        for ($i = 2; $i < $count; $i++) {
            if ($tokens[$i - 2][0] == T_CLASS
                && $tokens[$i - 1][0] == T_WHITESPACE
                && $tokens[$i][0] == T_STRING
            ) {

                $class_name = $tokens[$i][1];
                $classes[] = $class_name;
            }
        }

        return isset($classes[0]) ? $classes[0] : null;
    }

    /**
     * @param string $dir
     *
     * @return array
     */
    private function getDependenciesForDir(string $dir)
    {
        $astLocator = (new BetterReflection())->astLocator();
        $classLoader = require $this->projectDir . 'vendor/autoload.php';
        $reflector = new ClassReflector(new ComposerSourceLocator($classLoader, $astLocator));
        $dir = $this->projectDir . $dir;
        $collect = [];
        foreach ($this->rSearch($dir, "/.*\.php$/") as $filename) {
            try {
                $className = $this->getClassFullNameFromFile($filename);
                $classInfo = (new BetterReflection())->classReflector()->reflect($className);
                //        --- current module ---
                $currentModule = $this->getModuleName($classInfo);
                if (!$currentModule) {
                    continue;
                }
                if ($currentModule && !isset($collect[$currentModule])) {
                    $collect[$currentModule] = [];
                }
                //        --- parent module ---
                $parentModule = $this->getModuleName($classInfo->getParentClass());
                if ($parentModule && $parentModule != $currentModule) {
                    $collect[$currentModule][] = $parentModule;
                }
                //        --- constructor dependencies ---
                foreach ($this->getConstructorDependencies($classInfo, $currentModule) as $constructorDependency) {
                    $collect[$currentModule][] = $constructorDependency;
                }
                //        --- interface dependencies ---
                foreach ($this->getInterfaceDependencies($classInfo, $currentModule) as $interfaceDependency) {
                    $collect[$currentModule][] = $interfaceDependency;
                }
                //        --- unique dependencies ---
                $collect[$currentModule] = array_unique($collect[$currentModule]);
            } catch (\Exception $exception) {
                $filename = str_replace($dir . '/', '', $filename);
                $this->skipped[] = $filename;
            }
        }
        foreach ($this->rSearch($dir, "/.*module.xml/") as $filename) {
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
                }
            }
            $collect[$moduleName] = array_unique($collect[$moduleName]);
        }

        return $collect;
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
     * @param $folder
     * @param $pattern
     *
     * @return array
     */
    private function rSearch($folder, $pattern)
    {
        $fileList = [];
        if (!is_dir($folder)) {
            return $fileList;
        }
        $dir = new \RecursiveDirectoryIterator($folder);
        $ite = new \RecursiveIteratorIterator($dir);
        $files = new \RegexIterator($ite, $pattern, \RegexIterator::GET_MATCH);
        foreach ($files as $file) {
            $fileName = is_array($file) && isset($file[0]) ? $file[0] : (string)$file;
            if ($this->isSkipFile($fileName)) {
                continue;
            }
            $fileList[] = $fileName;
        }

        return $fileList;
    }

    /**
     * @param $fileName
     *
     * @return bool
     */
    private function isSkipFile($fileName)
    {
        $path = explode(DIRECTORY_SEPARATOR, $fileName);
        foreach ($this->foldersToSkip as $needle) {
            if (in_array($needle, $path)) {
                return true;
            }
        }

        return false;
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
