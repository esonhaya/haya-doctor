<?php

declare(strict_types=1);

namespace Tools\Doctor\Python;

final class PythonSyntaxChecker
{
    public function check(
        string $root,
        string $relativePath,
        string $python
    ): array {
        $absolutePath =
            PythonFileScanner::absolutePath(
                $root,
                $relativePath
            );

        $result = PythonRuntime::run(
            $python,
            [
                '-m',
                'py_compile',
                $absolutePath,
            ]
        );

        if ($result['ok']) {
            return [
                'ok' => true,
                'status' => 'PASS',
                'error' => null,
            ];
        }

        if ($result['timeout']) {
            return [
                'ok' => false,
                'status' => 'TIMEOUT',
                'error' =>
                    'Syntax compilation exceeded '
                    . PythonRuntime::DEFAULT_TIMEOUT
                    . ' seconds.',
            ];
        }

        return [
            'ok' => false,
            'status' => 'FAIL',
            'error' =>
                $result['output'] !== ''
                    ? $result['output']
                    : 'Python syntax compilation failed.',
        ];
    }
}
