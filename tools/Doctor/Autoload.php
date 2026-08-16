<?php

declare(strict_types=1);

spl_autoload_register(
    static function (string $class): void {
        $prefix = 'Tools\\Doctor\\';

        if (
            !str_starts_with(
                $class,
                $prefix
            )
        ) {
            return;
        }

        $relative =
            substr(
                $class,
                strlen($prefix)
            );

        if ($relative === false) {
            return;
        }

        $file =
            __DIR__
            . '/'
            . str_replace(
                '\\',
                '/',
                $relative
            )
            . '.php';

        if (is_file($file)) {
            require_once $file;
        }
    }
);
