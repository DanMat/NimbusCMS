<?php

declare(strict_types=1);

/**
 * Standalone PSR-4 autoloader for the Panelix\ namespace.
 *
 * Composer users get autoloading from composer.json and never need this. Apps
 * that don't run Composer can `require 'panelix/autoload.php';` instead — handy
 * for dropping the CMS into a legacy project during migration.
 */
spl_autoload_register(static function (string $class): void {
    $prefix = 'Panelix\\';
    $len = strlen($prefix);
    if (strncmp($class, $prefix, $len) !== 0) {
        return;
    }
    $file = __DIR__ . '/src/' . str_replace('\\', '/', substr($class, $len)) . '.php';
    if (is_file($file)) {
        require $file;
    }
});
