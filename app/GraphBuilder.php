<?php

namespace Zamoroka\MagentoDependencyGraph;

class GraphBuilder
{
    /** @var DependencyCollector */
    private $dependencyCollector;
    /** @var array */
    private $dependencies;
    /** @var string|null */
    private $vendor;

    /**
     * GraphBuilder constructor.
     *
     * @param DependencyCollector $dependencyCollector
     * @param string|null $vendor module vendor e.g. Magento
     */
    public function __construct(DependencyCollector $dependencyCollector, string $vendor = null)
    {
        $this->dependencyCollector = $dependencyCollector;
        $this->vendor = $vendor;
        $this->dependencies = $this->dependencyCollector->getDependencies();
    }

    /**
     * Generate content of .dot file
     * Drawing graphs with dot: https://graphviz.org/pdf/dotguide.pdf
     * @return string
     */
    public function getDotContent()
    {
        $content = 'digraph "' . date('Y-m-d') . '" {' . PHP_EOL;
        $content .= ' graph [rankdir = LR];' . PHP_EOL;
        $content .= ' splines=line;' . PHP_EOL;
        $content .= ' node [style=filled,fillcolor=white,shape=rect];' . PHP_EOL;
        foreach ($this->dependencies as $key => $dependency) {
            foreach ($dependency as $item) {
                if (array_key_exists($item, $this->dependencies)) {
                    $both = in_array($key, $this->dependencies[$item]);
                    $arrColor = $both ? ' color="red"' : '';
                    $content .= ' ' . $item . ' -> ' . $key . ' [dir=back' . $arrColor . '];' . PHP_EOL;
                }
            }
        }
        foreach ($this->dependencyCollector->collectAllModules() as $module) {
            if ($this->vendor && strpos($module, $this->vendor) === false) {
                continue;
            }
            $nodeStyling = [];
            if ($this->dependencyCollector->isIndependent($module, $this->dependencies)) {
                $nodeStyling[] = 'fillcolor=lightblue';
            }
            if ($this->dependencyCollector->isInVendor($module)) {
                $nodeStyling[] = 'label=' . $module;
                $nodeStyling[] = 'fontcolor=darkgreen';
            } else {
                $nodeStyling[] = 'label=' . $module;
                $nodeStyling[] = 'fontcolor=darkorange';
            }
            if ($nodeStyling) {
                $content .= ' ' . $module . ' [' . implode(',', $nodeStyling) . ']' . PHP_EOL;
            }
        }
        $content .= '}';

        return $content;
    }
}
