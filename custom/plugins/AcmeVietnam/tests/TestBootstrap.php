<?php declare(strict_types=1);

use Shopware\Core\TestBootstrapper;

// Không addCallingPlugin(): không cài AcmeVietnam vào DB test dùng chung (xem phpunit.xml).
$loader = (new TestBootstrapper())
    ->bootstrap()
    ->getClassLoader();

$loader->addPsr4('Acme\\Vietnam\\', __DIR__ . '/../src');
$loader->addPsr4('Acme\\Vietnam\\Tests\\', __DIR__);
