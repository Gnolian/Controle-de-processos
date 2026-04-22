<?php

declare(strict_types=1);

session_start();

$config = require __DIR__ . '/config.php';

spl_autoload_register(static function (string $class): void {
    $path = __DIR__ . '/' . str_replace('\\', '/', $class) . '.php';
    if (is_file($path)) {
        require $path;
    }
});

require __DIR__ . '/helpers.php';

