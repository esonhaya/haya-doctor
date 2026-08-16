<?php

declare(strict_types=1);

namespace Tools\Doctor\Registry;

use ReflectionClass;

final class AnalyzerRegistry
{
    /**
     * @param string $directory
     * @return array<int,object>
     */
    public function fromDirectory(
        string $directory
    ): array {
        $analyzers = [];

        if (!is_dir($directory)) {
            return [];
        }

        foreach (
            glob(
                rtrim($directory, DIRECTORY_SEPARATOR)
                . DIRECTORY_SEPARATOR
                . '*.php'
            ) ?: []
            as $file
        ) {

            $class =
                'Tools\\Doctor\\Analyzers\\'
                . basename($file, '.php');

            if (!class_exists($class)) {
                require_once $file;
            }

            if (!class_exists($class)) {
                continue;
            }

            $reflection =
                new ReflectionClass($class);

            if (
                $reflection->isAbstract()
            ) {
                continue;
            }

            if (
                !$reflection->hasMethod(
                    'analyze'
                )
            ) {
                continue;
            }

            $analyzers[] =
                new $class();

        }

        usort(
            $analyzers,
            fn($a, $b) =>
                strcmp(
                    get_class($a),
                    get_class($b)
                )
        );

        return $analyzers;
    }

    public function all(
        string $directory = ''
    ): array {
        if ($directory === '') {
            return [];
        }

        return $this->fromDirectory($directory);
    }
}
