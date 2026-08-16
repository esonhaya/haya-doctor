<?php

declare(strict_types=1);

namespace Tools\Doctor\Python;

final class PythonModuleResolver
{
    public static function moduleFromPath(
        string $relativePath
    ): ?string {
        $path = trim(
            str_replace('\\', '/', $relativePath),
            '/'
        );

        if (!str_ends_with($path, '.py')) {
            return null;
        }

        $module = substr(
            $path,
            0,
            -3
        );

        if (str_ends_with($module, '/__init__')) {
            $module = substr(
                $module,
                0,
                -9
            );
        }

        $module = str_replace('/', '.', $module);

        if ($module === '') {
            return null;
        }

        return $module;
    }

    public static function importable(
        string $root,
        string $relativePath
    ): bool {
        $module = self::moduleFromPath($relativePath);

        if ($module === null) {
            return false;
        }

        $parts = explode('.', $module);

        foreach ($parts as $part) {
            if ($part === '' || !preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $part)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return string[]
     */
    public static function modules(
        ?string $root = null
    ): array {
        $root ??= getcwd();

        $modules = [];

        foreach (PythonFileScanner::files($root) as $file) {
            $module = self::moduleFromPath($file);

            if (
                $module !== null
                && self::importable($root, $file)
            ) {
                $modules[] = $module;
            }
        }

        sort($modules);

        return array_values(
            array_unique($modules)
        );
    }
}
