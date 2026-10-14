<?php

declare(strict_types=1);

namespace Tools\Doctor\Python;

final class PythonSyntaxChecker
{
    /**
     * Validate one Python source file.
     *
     * @return array{
     *     ok: bool,
     *     status: string,
     *     error: ?string
     * }
     */
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

    /**
     * Validate multiple Python files in one Python process.
     *
     * This is the preferred API for project-wide syntax validation.
     *
     * @param string[] $relativePaths
     *
     * @return array{
     *     ok: bool,
     *     checked: int,
     *     failures: array<int,array{file:string,error:string}>,
     *     output: string
     * }
     */
    public function checkFiles(
        string $root,
        array $relativePaths,
        string $python
    ): array {
        if ($relativePaths === []) {
            return [
                'ok' => true,
                'checked' => 0,
                'failures' => [],
                'output' => '',
            ];
        }

        $payload = base64_encode(
            json_encode(
                array_values($relativePaths),
                JSON_THROW_ON_ERROR
            )
        );

        $script = <<<'PY'
import base64
import json
import pathlib
import sys

root = pathlib.Path(sys.argv[1])

paths = json.loads(
    base64.b64decode(
        sys.argv[2]
    ).decode("utf-8")
)

failures = []

for relative in paths:
    path = root / relative

    try:
        source = path.read_text(
            encoding="utf-8"
        )

        compile(
            source,
            str(path),
            "exec"
        )

    except Exception as exc:
        failures.append(
            {
                "file": relative,
                "error": str(exc),
            }
        )

print(
    json.dumps(
        {
            "checked": len(paths),
            "failures": failures,
        }
    )
)

sys.exit(
    1 if failures else 0
)
PY;

        $result = PythonRuntime::run(
            $python,
            [
                '-c',
                $script,
                $root,
                $payload,
            ],
            PythonRuntime::DEFAULT_TIMEOUT
        );

        if ($result['timeout']) {
            return [
                'ok' => false,
                'checked' => 0,
                'failures' => [
                    [
                        'file' => '',
                        'error' =>
                            'Python syntax batch timed out.',
                    ],
                ],
                'output' => $result['output'],
            ];
        }

        $output = trim(
            $result['output']
        );

        $decoded = json_decode(
            $output,
            true
        );

        if (
            !is_array($decoded)
            || !isset($decoded['checked'])
            || !isset($decoded['failures'])
            || !is_array($decoded['failures'])
        ) {
            return [
                'ok' => false,
                'checked' => 0,
                'failures' => [
                    [
                        'file' => '',
                        'error' =>
                            'Invalid Python syntax batch response.'
                            . (
                                $output !== ''
                                    ? ' ' . $output
                                    : ''
                            ),
                    ],
                ],
                'output' => $output,
            ];
        }

        $failures = [];

        foreach (
            $decoded['failures']
            as $failure
        ) {
            $failures[] = [
                'file' =>
                    (string) (
                        $failure['file'] ?? ''
                    ),
                'error' =>
                    (string) (
                        $failure['error'] ?? ''
                    ),
            ];
        }

        return [
            'ok' => $result['ok'],
            'checked' => (int) $decoded['checked'],
            'failures' => $failures,
            'output' => $output,
        ];
    }
}
