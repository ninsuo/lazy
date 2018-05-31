#!/usr/bin/php
<?php

if (file_exists($autoload = __DIR__.'/../../../autoload.php')) {
    require_once $autoload;
} else {
    require_once __DIR__.'/vendor/autoload.php';
}

date_default_timezone_set('UTC');

// --- This command is made for roots

if (exec('whoami') !== 'root') {
    die('This command should be run as root.'.PHP_EOL);
}

// --- Preparing service container

$container = new \Pimple\Container();

function rglob($pattern, $flags = 0)
{
    $files = glob($pattern, $flags);
    foreach (glob(dirname($pattern).'/*', GLOB_ONLYDIR | GLOB_NOSORT) as $dir) {
        $files = array_merge($files, rglob($dir.'/'.basename($pattern), $flags));
    }

    return $files;
}

foreach (rglob('src/*ServiceProvider.php') as $command) {
    $class = str_replace(['/', 'src', '.php'], ['\\', 'Lazy', ''], $command);
    $container->register(new $class());
}

// --- Preparing configuration

$config = __DIR__.'/config.yml';
if (!is_file($config) || !is_readable($config)) {
    die('Please copy config.yml.dist to config.yml'.PHP_EOL);
}

$container['config'] = Symfony\Component\Yaml\Yaml::parse(file_get_contents($config));
if (!is_array($container['config'])) {
    die('Parsed configuration should be an array of key / value pairs.'.PHP_EOL);
}

// --- Loading all command configurations and launching the console

$configuration = new \Lazy\Core\Configuration();

foreach ($container->keys() as $key) {
    if (substr($key, -14) === '.configuration') {
        $container[$key]->build($configuration);
    }
}

$configuration->addEventListener(\Webmozart\Console\Api\Event\ConsoleEvents::PRE_HANDLE,
    function (\Webmozart\Console\Api\Event\PreHandleEvent $e) use ($container) {
        $container['io'] = $e->getIO();
});

$console = new \Webmozart\Console\ConsoleApplication($configuration);

try {
    $console->run();
} catch (\Lazy\Core\Exception\StopExecutionException $e) {
    return 1;
}

