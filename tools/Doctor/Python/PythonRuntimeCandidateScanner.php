<?php

declare(strict_types=1);

namespace Tools\Doctor\Python;

final class PythonRuntimeCandidateScanner
{
    public function __construct(
        private readonly ?PythonRuntimePolicy $policy = null
    ) {
    }

    /**
     * @return string[]
     */
    public function files(?string $root = null): array
    {
        $root ??= getcwd();

        $policy = $this->policy ?? new PythonRuntimePolicy();

        $candidates = [];

        foreach (PythonFileScanner::files($root) as $file) {
            if (!$policy->isRuntimeCandidate($file)) {
                continue;
            }

            $candidates[] = $file;
        }

        sort($candidates);

        return array_values(
            array_unique($candidates)
        );
    }

    /**
     * @return string[]
     */
    public function modules(?string $root = null): array
    {
        $root ??= getcwd();

        $modules = [];

        foreach ($this->files($root) as $file) {
            $module =
                PythonModuleResolver::moduleFromPath($file);

            if (
                $module === null
                || !PythonModuleResolver::importable(
                    $root,
                    $file
                )
            ) {
                continue;
            }

            $modules[$module] = true;
        }

        $modules = array_keys($modules);

        sort($modules);

        return $modules;
    }

    /**
     * @return array{
     *     files: string[],
     *     modules: string[],
     *     fileCount: int,
     *     moduleCount: int
     * }
     */
    public function scan(?string $root = null): array
    {
        $root ??= getcwd();

        $files = $this->files($root);
        $modules = $this->modules($root);

        return [
            'files' => $files,
            'modules' => $modules,
            'fileCount' => count($files),
            'moduleCount' => count($modules),
        ];
    }
}
