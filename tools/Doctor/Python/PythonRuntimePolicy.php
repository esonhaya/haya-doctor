<?php

declare(strict_types=1);

namespace Tools\Doctor\Python;

final class PythonRuntimePolicy
{
    /**
     * Determine whether a Python path represents a test source.
     */
    public function isTest(string $relativePath): bool
    {
        $path = $this->normalize($relativePath);
        $basename = basename($path);

        if (
            $this->hasPathSegment($path, 'tests')
            || $this->hasPathSegment($path, 'test')
        ) {
            return true;
        }

        if ($basename === 'conftest.py') {
            return true;
        }

        if (
            str_starts_with($basename, 'test_')
            || str_ends_with($basename, '_test.py')
        ) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether a path is generated Python bytecode or cache data.
     */
    public function isArchive(string $relativePath): bool
    {
        $path = $this->normalize($relativePath);
        $basename = basename($path);

        if (
            str_ends_with($basename, '.pyc')
            || str_ends_with($basename, '.pyo')
        ) {
            return true;
        }

        return $this->hasPathSegment($path, '__pycache__');
    }

    /**
     * Determine whether a Python source file is intentionally outside
     * the runtime application surface.
     */
    public function isExcluded(string $relativePath): bool
    {
        $path = $this->normalize($relativePath);

        if ($path === '') {
            return true;
        }

        if (
            $this->hasPathSegment($path, '.git')
            || $this->hasPathSegment($path, 'venv')
            || $this->hasPathSegment($path, '.venv')
            || $this->hasPathSegment($path, 'env')
            || $this->hasPathSegment($path, '.env')
            || $this->hasPathSegment($path, '__pycache__')
        ) {
            return true;
        }

        /*
         * Doctor tooling is outside the application runtime
         * runtime. PythonFileScanner already excludes tools/, but the
         * policy remains explicit so callers can use it independently.
         */
        if (
            $path === 'tools'
            || str_starts_with($path, 'tools/')
        ) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether a path is a recognized application entrypoint.
     */
    public function isEntrypoint(string $relativePath): bool
    {
        $path = $this->normalize($relativePath);

        return in_array(
            $path,
            [
                'main.py',
                'app.py',
                'run.py',
                'manage.py',
                'cli.py',
                'app/main.py',
            ],
            true
        );
    }

    /**
     * A runtime candidate is an application Python source file that is
     * neither test code nor generated/excluded content.
     */
    public function isRuntimeCandidate(string $relativePath): bool
    {
        $path = $this->normalize($relativePath);

        if ($path === '' || !str_ends_with($path, '.py')) {
            return false;
        }

        if ($this->isExcluded($path)) {
            return false;
        }

        if ($this->isTest($path)) {
            return false;
        }

        return true;
    }

    /**
     * Syntax candidates include all real Python source files.
     */
    public function isSyntaxCandidate(string $relativePath): bool
    {
        $path = $this->normalize($relativePath);

        if ($path === '' || !str_ends_with($path, '.py')) {
            return false;
        }

        return !$this->isExcluded($path);
    }

    /**
     * @return array{
     *     test: bool,
     *     archive: bool,
     *     excluded: bool,
     *     entrypoint: bool,
     *     syntax: bool,
     *     runtime: bool
     * }
     */
    public function classify(string $relativePath): array
    {
        return [
            'test' => $this->isTest($relativePath),
            'archive' => $this->isArchive($relativePath),
            'excluded' => $this->isExcluded($relativePath),
            'entrypoint' => $this->isEntrypoint($relativePath),
            'syntax' => $this->isSyntaxCandidate($relativePath),
            'runtime' => $this->isRuntimeCandidate($relativePath),
        ];
    }

    private function normalize(string $path): string
    {
        return trim(
            str_replace('\\', '/', $path),
            '/'
        );
    }

    private function hasPathSegment(
        string $path,
        string $segment
    ): bool {
        return in_array(
            $segment,
            explode('/', trim($path, '/')),
            true
        );
    }
}
