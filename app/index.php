<?php
if (PHP_SAPI !== 'cli') {
    throw new \Exception("CLI execution only");
}
$options = getopt('', ['magento-dir:', 'module-vendor:']);
require_once $options['magento-dir'] . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'bootstrap.php';
require_once __DIR__ . '/DependencyCollector.php';
require_once __DIR__ . '/GraphBuilder.php';
$dependencyClass = new DependencyCollector($options['magento-dir'], $options['module-vendor']);
$graphBuilder = new GraphBuilder($dependencyClass, $options['module-vendor']);
echo $graphBuilder->getDotContent();
