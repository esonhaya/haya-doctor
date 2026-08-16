<?php

declare(strict_types=1);

namespace Tools\Doctor\Checks;

use Tools\Doctor\Contracts\CheckInterface;
use Tools\Doctor\DTO\CheckResult;
use Tools\Doctor\Python\PythonProjectInspector;
use Tools\Doctor\Python\PythonRuntime;

final class PythonRuntimeCheck implements CheckInterface
{
    private const RUNTIME_TIMEOUT = 15;

    public function __construct(
        private readonly ?string $root = null
    ) {
    }

    public function run(): CheckResult
    {
        $python =
            PythonRuntime::executable();

        if ($python === null) {
            return new CheckResult(
                title:
                    'Python Runtime Integrity',
                status: 'FAIL',
                summary:
                    'Python executable was not found.',
                details: [],
                recommendations: [
                    'Install Python or configure a Python executable.',
                ],
                score: 0,
            );
        }

        $root =
            $this->root ?? getcwd();

        $inspection =
            (new PythonProjectInspector())
                ->inspect($root);

        $files =
            $inspection['files'] ?? [];

        $syntaxCandidates =
            $inspection['syntaxCandidates'] ?? [];

        $runtimeCandidates =
            $inspection['runtimeCandidates'] ?? [];

        $runtimeModules =
            $inspection['runtimeModules'] ?? [];

        $testFiles =
            $inspection['testFiles'] ?? [];

        $archiveFiles =
            $inspection['archiveFiles'] ?? [];

        $entrypoints =
            $inspection['entrypoints'] ?? [];

        if ($runtimeModules === []) {
            return new CheckResult(
                title:
                    'Python Runtime Integrity',
                status: 'PASS',
                summary:
                    'No Python runtime modules discovered.',
                details: [
                    'Python executable: ' . $python,
                    'Python version: '
                        . (
                            PythonRuntime::version($python)
                            ?? 'unknown'
                        ),
                    'Python files discovered: '
                        . count($files),
                    'Syntax candidates: '
                        . count($syntaxCandidates),
                    'Runtime candidates: '
                        . count($runtimeCandidates),
                    'Test files: '
                        . count($testFiles),
                    'Archive files: '
                        . count($archiveFiles),
                    'Entrypoints: '
                        . count($entrypoints),
                    'Runtime modules tested: 0',
                    'Successful imports: 0',
                    'Failed imports: 0',
                    'Runtime import elapsed: 0 seconds',
                    'Python processes: 0',
                ],
                recommendations: [
                    'Continue with Python syntax validation.',
                ],
                score: 100,
            );
        }

        $start =
            microtime(true);

        $result =
            $this->importModules(
                $root,
                $runtimeModules,
                $python
            );

        $elapsed =
            microtime(true) - $start;

        $failedImports =
            $result['failures'];

        $successfulImports =
            $result['checked']
            - count($failedImports);

        $details = [
            'Python executable: ' . $python,
            'Python version: '
                . (
                    PythonRuntime::version($python)
                    ?? 'unknown'
                ),
            'Python files discovered: '
                . count($files),
            'Syntax candidates: '
                . count($syntaxCandidates),
            'Runtime candidates: '
                . count($runtimeCandidates),
            'Test files: '
                . count($testFiles),
            'Archive files: '
                . count($archiveFiles),
            'Entrypoints: '
                . count($entrypoints),
            'Runtime modules tested: '
                . $result['checked'],
            'Successful imports: '
                . $successfulImports,
            'Failed imports: '
                . count($failedImports),
            'Runtime import elapsed: '
                . number_format($elapsed, 3)
                . ' seconds',
            'Python processes: 1',
        ];

        if (
            $result['timeout']
            || $result['protocolError']
        ) {
            $details[] =
                'Runtime scanner error: '
                . $result['error'];

            return new CheckResult(
                title:
                    'Python Runtime Integrity',
                status: 'FAIL',
                summary:
                    $result['timeout']
                        ? 'Python runtime import scan exceeded the timeout.'
                        : 'Python runtime import scan returned an invalid result.',
                details: $details,
                recommendations: [
                    'Review Python runtime availability and import performance.',
                    'Run Doctor again after correcting the runtime condition.',
                ],
                score: 20,
            );
        }

        if ($failedImports === []) {
            return new CheckResult(
                title:
                    'Python Runtime Integrity',
                status: 'PASS',
                summary:
                    $result['checked']
                    . ' Python runtime module(s) passed import.',
                details: $details,
                recommendations: [
                    'Continue with Python syntax validation.',
                ],
                score: 100,
            );
        }

        foreach (
            $failedImports
            as $failure
        ) {
            $details[] =
                $failure['module']
                . ' — '
                . $failure['error'];
        }

        return new CheckResult(
            title:
                'Python Runtime Integrity',
            status: 'FAIL',
            summary:
                count($failedImports)
                . ' Python runtime module(s) failed import.',
            details: $details,
            recommendations: [
                'Fix failed Python imports or environment dependencies.',
                'Review PythonRuntimePolicy if application modules are incorrectly classified.',
                'Review PythonModuleResolver importability rules.',
                'Use PythonSyntaxCheck for source-only validation.',
                'Run Doctor again after correcting the root failure.',
            ],
            score: 15,
        );
    }

    /**
     * @param string[] $modules
     *
     * @return array{
     *     checked: int,
     *     failures: array<int,array{module:string,error:string}>,
     *     timeout: bool,
     *     protocolError: bool,
     *     error: string
     * }
     */
    private function importModules(
        string $root,
        array $modules,
        string $python
    ): array {
        $payload =
            base64_encode(
                json_encode(
                    array_values($modules),
                    JSON_THROW_ON_ERROR
                )
            );

        $script = <<<'PY'
import base64
import importlib
import json
import os
import sys
import traceback

root = sys.argv[1]

modules = json.loads(
    base64.b64decode(
        sys.argv[2]
    ).decode("utf-8")
)

os.chdir(root)

if root not in sys.path:
    sys.path.insert(0, root)

failures = []

for module in modules:
    try:
        importlib.import_module(module)
    except Exception as exc:
        failures.append(
            {
                "module": module,
                "error": (
                    type(exc).__name__
                    + ": "
                    + str(exc)
                ),
            }
        )

print(
    json.dumps(
        {
            "checked": len(modules),
            "failures": failures,
        }
    )
)

sys.exit(1 if failures else 0)
PY;

        $result =
            PythonRuntime::run(
                $python,
                [
                    '-c',
                    $script,
                    $root,
                    $payload,
                ],
                self::RUNTIME_TIMEOUT
            );

        if ($result['timeout']) {
            return [
                'checked' => 0,
                'failures' => [],
                'timeout' => true,
                'protocolError' => false,
                'error' =>
                    $result['output'] !== ''
                        ? $result['output']
                        : 'Python runtime import scan timed out.',
            ];
        }

        $output =
            trim(
                $result['output']
            );

        $decoded =
            json_decode(
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
                'checked' => 0,
                'failures' => [],
                'timeout' => false,
                'protocolError' => true,
                'error' =>
                    'Invalid Python runtime scan response.'
                    . (
                        $output !== ''
                            ? ' ' . $output
                            : ''
                    ),
            ];
        }

        $failures = [];

        foreach (
            $decoded['failures']
            as $failure
        ) {
            $failures[] = [
                'module' =>
                    (string) (
                        $failure['module']
                        ?? ''
                    ),
                'error' =>
                    (string) (
                        $failure['error']
                        ?? ''
                    ),
            ];
        }

        return [
            'checked' =>
                (int) $decoded['checked'],
            'failures' =>
                $failures,
            'timeout' => false,
            'protocolError' => false,
            'error' => '',
        ];
    }

    public function category(): string
    {
        return 'python';
    }

    public function priority(): int
    {
        return 30;
    }
}
