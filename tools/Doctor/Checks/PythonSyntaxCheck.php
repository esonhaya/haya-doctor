<?php

declare(strict_types=1);

namespace Tools\Doctor\Checks;

use Tools\Doctor\Contracts\CheckInterface;
use Tools\Doctor\DTO\CheckResult;
use Tools\Doctor\Python\PythonFileScanner;
use Tools\Doctor\Python\PythonRuntime;
use Tools\Doctor\Python\PythonSyntaxChecker;

final class PythonSyntaxCheck implements CheckInterface
{
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
                title: 'Python Syntax Integrity',
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

        $files =
            PythonFileScanner::files($root);

        if ($files === []) {
            return new CheckResult(
                title: 'Python Syntax Integrity',
                status: 'PASS',
                summary: 'No Python source files discovered.',
                details: [
                    'Python executable: ' . $python,
                ],
                recommendations: [],
                score: 100,
            );
        }

        $checker =
            new PythonSyntaxChecker();

        $failures = [];
        $timeouts = [];
        $passed = 0;

        foreach ($files as $file) {
            $result =
                $checker->check(
                    $root,
                    $file,
                    $python
                );

            if ($result['ok']) {
                $passed++;
                continue;
            }

            if ($result['status'] === 'TIMEOUT') {
                $timeouts[$file] =
                    $result['error'];
            } else {
                $failures[$file] =
                    $result['error'];
            }
        }

        if (
            $failures === []
            && $timeouts === []
        ) {
            return new CheckResult(
                title: 'Python Syntax Integrity',
                status: 'PASS',
                summary:
                    count($files)
                    . ' Python file(s) compiled successfully.',
                details: [
                    'Python executable: ' . $python,
                    'Python files: ' . count($files),
                    'Successful compilation: ' . $passed,
                ],
                recommendations: [],
                score: 100,
            );
        }

        $details = [
            'Python executable: ' . $python,
            'Python files: ' . count($files),
            'Successful compilation: ' . $passed,
            'Syntax failures: ' . count($failures),
            'Timeouts: ' . count($timeouts),
        ];

        foreach ($failures as $file => $error) {
            $details[] =
                'FAIL ' . $file . ' — ' . $error;
        }

        foreach ($timeouts as $file => $error) {
            $details[] =
                'TIMEOUT ' . $file . ' — ' . $error;
        }

        return new CheckResult(
            title: 'Python Syntax Integrity',
            status: 'FAIL',
            summary:
                'One or more Python files failed syntax validation.',
            details: $details,
            recommendations: [
                'Fix Python syntax errors first.',
                'Run Doctor again after correcting the failures.',
            ],
            score: 20,
        );
    }

    public function category(): string
    {
        return 'python';
    }

    public function priority(): int
    {
        return 20;
    }
}
