<?php

declare(strict_types=1);

namespace Tools\Doctor\Python;

final class PythonProjectInspector
{
    public function __construct(
        private readonly ?PythonRuntimePolicy $policy = null,
        private readonly ?PythonRuntimeCandidateScanner $candidateScanner = null
    ) {
    }

    /**
     * @return array{
     *     root: string,
     *     files: string[],
     *     fileCount: int,
     *     syntaxCandidates: string[],
     *     syntaxCandidateCount: int,
     *     runtimeCandidates: string[],
     *     runtimeCandidateCount: int,
     *     runtimeModules: string[],
     *     runtimeModuleCount: int,
     *     testFiles: string[],
     *     testFileCount: int,
     *     archiveFiles: string[],
     *     archiveFileCount: int,
     *     entrypoints: string[],
     *     entrypointCount: int
     * }
     */
    public function inspect(
        ?string $root = null
    ): array {
        $root ??= getcwd();

        $policy =
            $this->policy
            ?? new PythonRuntimePolicy();

        $candidateScanner =
            $this->candidateScanner
            ?? new PythonRuntimeCandidateScanner($policy);

        $files =
            PythonFileScanner::files($root);

        $syntaxCandidates = [];
        $testFiles = [];
        $archiveFiles = [];
        $entrypoints = [];

        foreach ($files as $file) {
            $classification =
                $policy->classify($file);

            if ($classification['syntax']) {
                $syntaxCandidates[] = $file;
            }

            if ($classification['test']) {
                $testFiles[] = $file;
            }

            if ($classification['archive']) {
                $archiveFiles[] = $file;
            }

            if ($classification['entrypoint']) {
                $entrypoints[] = $file;
            }
        }

        $runtimeCandidates =
            $candidateScanner->files($root);

        $runtimeModules =
            $candidateScanner->modules($root);

        sort($syntaxCandidates);
        sort($testFiles);
        sort($archiveFiles);
        sort($entrypoints);

        return [
            'root' => $root,

            'files' => $files,
            'fileCount' => count($files),

            'syntaxCandidates' => $syntaxCandidates,
            'syntaxCandidateCount' => count($syntaxCandidates),

            'runtimeCandidates' => $runtimeCandidates,
            'runtimeCandidateCount' => count($runtimeCandidates),

            'runtimeModules' => $runtimeModules,
            'runtimeModuleCount' => count($runtimeModules),

            'testFiles' => $testFiles,
            'testFileCount' => count($testFiles),

            'archiveFiles' => $archiveFiles,
            'archiveFileCount' => count($archiveFiles),

            'entrypoints' => $entrypoints,
            'entrypointCount' => count($entrypoints),
        ];
    }
}
