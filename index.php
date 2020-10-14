<?php

use Magento\Framework\App\Bootstrap;
use Zamoroka\MagentoDependencyGraph\DependencyCollector;
use Zamoroka\MagentoDependencyGraph\GraphBuilder;

if (PHP_SAPI !== 'cli') {
    throw new \Exception("CLI execution only");
}
$options = getopt('', ['magento-dir:', 'module-vendor:']);
require_once $options['magento-dir'] . DIRECTORY_SEPARATOR . 'app/bootstrap.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'vendor/autoload.php';
$dependencyClass = new DependencyCollector($options['magento-dir'], $options['module-vendor']);
$graphBuilder = new GraphBuilder($dependencyClass, $options['module-vendor']);
echo $graphBuilder->getDotContent();
