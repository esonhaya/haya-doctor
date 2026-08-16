<?php

declare(strict_types=1);

namespace Tools\Doctor\Checks;

use Tools\Doctor\Contracts\CheckInterface;
use Tools\Doctor\DTO\CheckResult;
use Tools\Doctor\Python\PythonProjectInspector;
use Tools\Doctor\Python\PythonRuntime;

final class PythonRuntimeCheck implements CheckInterface
{
    public function __construct(
        private readonly ?string $root = null
    ) {
    }

    private const TIMEOUT = 5;

    public function run(): CheckResult
    {
        $python = PythonRuntime::executable();

        if ($python === null) {
            return new CheckResult(
                title: 'Python Runtime Integrity',
                status: 'FAIL',
                summary: 'Python executable was not found.',
                details: [],
                recommendations: [
                    'Install Python or configure a Python executable.',
                ],
                score: 0,
            );
        }

        $root = $this->root ?? getcwd();

        $inspection = (new PythonProjectInspector())
            ->inspect($root);

        $modules = $inspection['runtimeModules'];

        $baseDetails = [
            'Python executable: ' . $python,
            'Python version: '
                . (PythonRuntime::version($python) ?? 'unknown'),
            'Python files discovered: '
                . $inspection['fileCount'],
            'Syntax candidates: '
                . $inspection['syntaxCandidateCount'],
            'Runtime candidates: '
                . $inspection['runtimeCandidateCount'],
            'Test files: '
                . $inspection['testFileCount'],
            'Archive files: '
                . $inspection['archiveFileCount'],
            'Entrypoints: '
                . $inspection['entrypointCount'],
        ];

        if ($modules === []) {
            return new CheckResult(
                title: 'Python Runtime Integrity',
                status: 'WARN',
                summary:
                    'Python runtime candidates were discovered, but '
                    . 'no importable runtime modules were identified.',
                details: array_merge(
                    $baseDetails,
                    ['Importable runtime modules: 0']
                ),
                recommendations: [
                    'Review PythonRuntimePolicy if application modules '
                        . 'are being excluded unexpectedly.',
                    'Review PythonModuleResolver importability rules.',
                    'Use PythonSyntaxCheck for source-only validation.',
                ],
                score: 70,
            );
        }

        $result = $this->importModules(
            $python,
            $modules
        );

        $failures = $result['failures'];
        $passed = $result['passed'];

        $details = array_merge(
            $baseDetails,
            [
                'Runtime modules tested: ' . count($modules),
                'Successful imports: ' . $passed,
                'Failed imports: ' . count($failures),
                'Runtime import elapsed: '
                    . number_format($result['elapsed'], 3)
                    . ' seconds',
            ]
        );

        foreach ($failures as $module => $error) {
            $details[] = $module . ' — ' . $error;
        }

        if ($result['timeout']) {
            return new CheckResult(
                title: 'Python Runtime Integrity',
                status: 'FAIL',
                summary:
                    'Python runtime import scan exceeded the timeout.',
                details: $details,
                recommendations: [
                    'Review the reported Python import path for a '
                        . 'slow or blocking module.',
                    'Fix failed Python imports or environment dependencies.',
                    'Run Doctor again after correcting the root failure.',
                ],
                score: 15,
            );
        }

        if ($failures === []) {
            return new CheckResult(
                title: 'Python Runtime Integrity',
                status: 'PASS',
                summary:
                    count($modules)
                    . ' Python runtime module(s) passed import.',
                details: $details,
                recommendations: [],
                score: 100,
            );
        }

        return new CheckResult(
            title: 'Python Runtime Integrity',
            status: 'FAIL',
            summary:
                count($failures)
                . ' Python runtime module(s) failed import.',
            details: $details,
            recommendations: [
                'Fix failed Python imports or environment dependencies.',
                'Review runtime candidate classification if a test, '
                    . 'script, or optional module was incorrectly included.',
                'Run Doctor again after correcting the root failure.',
            ],
            score: 15,
        );
    }

    /**
     * @param string[] $modules
     *
     * @return array{
     *     failures: array<string, string>,
     *     passed: int,
     *     timeout: bool,
     *     elapsed: float
     * }
     */
    private function importModules(
        string $python,
        array $modules
    ): array {
        $encodedModules = base64_encode(
            json_encode($modules, JSON_THROW_ON_ERROR)
        );

        $script = <<<'PY'
import base64
import importlib
import json
import sys
import time

modules = json.loads(
    base64.b64decode(sys.argv[1]).decode("utf-8")
)

started = time.monotonic()
passed = 0
failures = {}

for module in modules:
    try:
        importlib.import_module(module)
        passed += 1
    except Exception as exc:
        failures[module] = (
            type(exc).__name__ + ": " + str(exc)
        )

payload = {
    "passed": passed,
    "failures": failures,
    "elapsed": time.monotonic() - started,
}

print(
    json.dumps(payload),
    flush=True,
)
PY;

        $result = PythonRuntime::run(
            $python,
            [
                '-c',
                $script,
                $encodedModules,
            ],
            self::TIMEOUT
        );

        if ($result['timeout']) {
            return [
                'failures' => [],
                'passed' => 0,
                'timeout' => true,
                'elapsed' => (float) self::TIMEOUT,
            ];
        }

        if (!$result['ok']) {
            return [
                'failures' => [
                    '__runtime__' =>
                        $result['output'] !== ''
                            ? $result['output']
                            : 'Python runtime scan failed.',
                ],
                'passed' => 0,
                'timeout' => false,
                'elapsed' => 0.0,
            ];
        }

        try {
            $payload = json_decode(
                $result['output'],
                true,
                512,
                JSON_THROW_ON_ERROR
            );
        } catch (Throwable $exception) {
            return [
                'failures' => [
                    '__runtime__' =>
                        'Invalid runtime scan output: '
                        . $exception->getMessage(),
                ],
                'passed' => 0,
                'timeout' => false,
                'elapsed' => 0.0,
            ];
        }

        return [
            'failures' => $payload['failures'] ?? [],
            'passed' => (int) ($payload['passed'] ?? 0),
            'timeout' => false,
            'elapsed' => (float) ($payload['elapsed'] ?? 0.0),
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

    /**
     * @return array{
     *     ok: bool,
     *     error: string
     * }
     */
    private function importModule(
        string $python,
        string $module
    ): array {
        $script = <<<'PY'
import importlib
import sys

try:
    importlib.import_module(sys.argv[1])
    print("OK")
except Exception as exc:
    print(type(exc).__name__ + ": " + str(exc))
    raise SystemExit(1)
PY;

        $result = PythonRuntime::run(
            $python,
            [
                '-c',
                $script,
                $module,
            ],
            self::TIMEOUT
        );

        if ($result['timeout']) {
            return [
                'ok' => false,
                'error' =>
                    'Import exceeded '
                    . self::TIMEOUT
                    . ' seconds.',
            ];
        }

        if ($result['ok']) {
            return [
                'ok' => true,
                'error' => '',
            ];
        }

        return [
            'ok' => false,
            'error' =>
                $result['output'] !== ''
                    ? $result['output']
                    : 'Python import failed.',
        ];
    }
}
