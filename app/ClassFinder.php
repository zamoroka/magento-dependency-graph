<?php

namespace Zamoroka\MagentoDependencyGraph;

class ClassFinder
{
    private $foldersToSkip = [
        'Test',
        'Tests',
        'tests',
        'Sniffs',
        'PHP_CodeSniffer',
        'Unit',
        'MagentoHackathon',
    ];

    /**
     * @param $dir
     *
     * @return array
     */
    public function getAllClasses($dir)
    {
        foreach ($this->rSearch($dir, "/.*\.php$/") as $filename) {
            try {
             yield [
                    'file_name' => $filename,
                    'class_name' => $this->getClassFullNameFromFile($filename),
                ];
            } catch (\Exception $exception) {
                // skip errors
            }
        }

    }

    /**
     * @param $folder
     * @param $pattern
     *
     * @return array
     */
    public function rSearch($folder, $pattern)
    {
        $fileList = [];
        if (!is_dir($folder)) {
            return $fileList;
        }
        $dir = new \RecursiveDirectoryIterator($folder, \RecursiveDirectoryIterator::FOLLOW_SYMLINKS);
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
     * @param $filePathName
     *
     * @return string
     * @throws \Exception
     */
    public function getClassFullNameFromFile($filePathName)
    {
        $className = $this->getClassNameFromFile($filePathName);
        if (!$className) {
            throw new \Exception("not a class");
        }

        return $this->getClassNamespaceFromFile($filePathName) . '\\' . $this->getClassNameFromFile($filePathName);
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
        $namespaceOk = false;
        while ($i < $count) {
            $token = $tokens[$i];
            if (is_array($token) && $token[0] === T_NAMESPACE) {
                // Found namespace declaration
                while (++$i < $count) {
                    if ($tokens[$i] === ';') {
                        $namespaceOk = true;
                        $namespace = trim($namespace);
                        break;
                    }
                    $namespace .= is_array($tokens[$i]) ? $tokens[$i][1] : $tokens[$i];
                }
                break;
            }
            $i++;
        }
        if (!$namespaceOk) {
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

                $className = $tokens[$i][1];
                $classes[] = $className;
            }
        }

        return isset($classes[0]) ? $classes[0] : null;
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
}
