<?php

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
            throw new Exception("not a class");
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
    private function getDependenciesForDir($dir)
    {
        $dir = $this->projectDir . $dir;
        $collect = [];
        foreach ($this->rSearch($dir, "/.*\.php$/") as $filename) {
            try {
                $className = $this->getClassFullNameFromFile($filename);
                $ref = new \ReflectionClass($className);
                //        --- current module ---
                $currentModule = $this->getModuleName($ref);
                if ($currentModule && !isset($collect[$currentModule])) {
                    $collect[$currentModule] = [];
                }
                //        --- parent module ---
                $parentClass = $ref->getParentClass() ? $ref->getParentClass() : null;
                $parentModule = $this->getModuleName($parentClass);
                if ($parentModule && $parentModule != $currentModule) {
                    $collect[$currentModule][] = $parentModule;
                }
                //        --- constructor dependencies ---
                $c = $ref->getConstructor();
                if ($c) {
                    foreach ($c->getParameters() as $p) {
                        if ($p->getClass()) {
                            $constructorDependency = $this->getModuleName($p->getClass());
                            if ($constructorDependency && $constructorDependency != $currentModule) {
                                $collect[$currentModule][] = $constructorDependency;
                            }
                        }
                    }
                }
                //        --- unique dependencies ---
                $collect[$currentModule] = array_unique($collect[$currentModule]);
            } catch (Exception $exception) {
                $filename = str_replace($dir . '/', '', $filename);
                $this->skipped[] = $filename;
            }
        }
        foreach ($this->rSearch($dir, "/.*module.xml/") as $filename) {
            $xml = file_get_contents($filename);
            $dom = new DOMDocument();
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
        $dir = new RecursiveDirectoryIterator($folder);
        $ite = new RecursiveIteratorIterator($dir);
        $files = new RegexIterator($ite, $pattern, RegexIterator::GET_MATCH);
        foreach ($files as $file) {
            $fileName = is_array($file) && isset($file[0]) ? $file[0] : (string)$file;
            if (strpos($fileName, 'Test') !== false) {
                continue;
            }
            $fileList[] = $fileName;
        }

        return $fileList;
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
