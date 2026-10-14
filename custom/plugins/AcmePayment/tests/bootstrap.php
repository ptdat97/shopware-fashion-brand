<?php declare(strict_types=1);

$loader = require __DIR__ . '/../../../../vendor/autoload.php';
$loader->addPsr4('Acme\\Payment\\', __DIR__ . '/../src');
$loader->addPsr4('Acme\\Payment\\Tests\\', __DIR__);
