<?php

declare(strict_types=1);

namespace Tools\Doctor\Registry;

use ReflectionClass;
use Tools\Doctor\Contracts\CheckInterface;

final class CheckRegistry
{
    /**
     * Register check directories supplied by the host.
     *
     * @param array<int,string> $directories
     * @return CheckInterface[]
     */
    public function fromDirectories(
        array $directories
    ): array {
        $checks = [];

        foreach ($directories as $directory) {
            if (!is_dir($directory)) {
                continue;
            }

            foreach (
                glob(
                    rtrim($directory, DIRECTORY_SEPARATOR)
                    . DIRECTORY_SEPARATOR
                    . '*.php'
                ) ?: []
                as $file
            ) {
                $class = $this->classFromFile($file);

                if ($class === null) {
                    continue;
                }

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
                    || !$reflection->implementsInterface(
                        CheckInterface::class
                    )
                ) {
                    continue;
                }

                $checks[] = new $class();
            }
        }

        usort(
            $checks,
            static fn(
                CheckInterface $a,
                CheckInterface $b
            ): int =>
                $a->priority()
                <=>
                $b->priority()
        );

        return $checks;
    }

    /**
     * @param array<int,string> $directories
     * @return CheckInterface[]
     */
    public function all(
        array $directories = []
    ): array {
        return $this->fromDirectories(
            $directories
        );
    }

    private function classFromFile(
        string $file
    ): ?string {
        $source = file_get_contents($file);

        if ($source === false) {
            return null;
        }

        if (
            preg_match(
                '/namespace\s+([^;]+);/s',
                $source,
                $namespace
            ) !== 1
        ) {
            return null;
        }

        if (
            preg_match(
                '/\bclass\s+([A-Za-z_][A-Za-z0-9_]*)\b/s',
                $source,
                $class
            ) !== 1
        ) {
            return null;
        }

        return trim($namespace[1])
            . '\\'
            . $class[1];
    }
}
