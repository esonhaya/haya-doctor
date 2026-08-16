<?php

declare(strict_types=1);

namespace Tools\Doctor\Python;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use FilesystemIterator;

final class PythonFileScanner
{
    /**
     * @return string[]
     */
    public static function files(?string $root = null): array
    {
        $root = $root ?? getcwd();

        if (!is_dir($root)) {
            return [];
        }

        $files = [];

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                $root,
                FilesystemIterator::SKIP_DOTS
            )
        );

        foreach ($iterator as $file) {
            if (
                !$file->isFile()
                || strtolower($file->getExtension()) !== 'py'
            ) {
                continue;
            }

            $path = str_replace('\\', '/', $file->getPathname());

            $relative = self::relativePath($root, $path);

            if (self::isExcluded($relative)) {
                continue;
            }

            $files[] = $relative;
        }

        sort($files);

        return array_values(array_unique($files));
    }

    public static function absolutePath(
        string $root,
        string $relative
    ): string {
        return rtrim($root, DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR
            . str_replace('/', DIRECTORY_SEPARATOR, $relative);
    }

    private static function relativePath(
        string $root,
        string $path
    ): string {
        $root = rtrim(
            str_replace('\\', '/', $root),
            '/'
        );

        $path = str_replace('\\', '/', $path);

        if (str_starts_with($path, $root . '/')) {
            return substr($path, strlen($root) + 1);
        }

        return $path;
    }

    private static function isExcluded(string $path): bool
    {
        $normalized = trim($path, '/');

        $excludedPrefixes = [
            'tools/',
            '.git/',
            'venv/',
            '.venv/',
            'env/',
            '.env/',
            '__pycache__/',
        ];

        foreach ($excludedPrefixes as $prefix) {
            if (str_starts_with($normalized, $prefix)) {
                return true;
            }
        }

        return str_contains(
            '/' . $normalized . '/',
            '/__pycache__/'
        );
    }
}
