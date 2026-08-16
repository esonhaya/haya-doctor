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
                title:
                    'Python Syntax Integrity',
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

        $files =
            PythonFileScanner::files(
                $root
            );

        if ($files === []) {
            return new CheckResult(
                title:
                    'Python Syntax Integrity',
                status: 'PASS',
                summary:
                    'No Python source files discovered.',
                details: [
                    'Python executable: ' . $python,
                ],
                recommendations: [],
                score: 100,
            );
        }

        $result =
            (new PythonSyntaxChecker())
                ->checkFiles(
                    $root,
                    $files,
                    $python
                );

        if ($result['ok']) {
            return new CheckResult(
                title:
                    'Python Syntax Integrity',
                status: 'PASS',
                summary:
                    $result['checked']
                    . ' Python file(s) compiled successfully.',
                details: [
                    'Python executable: ' . $python,
                    'Python files: '
                        . $result['checked'],
                    'Successful compilation: '
                        . $result['checked'],
                    'Failed compilation: 0',
                    'Python processes: 1',
                ],
                recommendations: [],
                score: 100,
            );
        }

        $details = [
            'Python executable: ' . $python,
            'Python files: '
                . $result['checked'],
            'Successful compilation: '
                . (
                    $result['checked']
                    - count($result['failures'])
                ),
            'Syntax failures: '
                . count($result['failures']),
            'Python processes: 1',
        ];

        foreach (
            $result['failures']
            as $failure
        ) {
            $details[] =
                'FAIL '
                . $failure['file']
                . ' — '
                . $failure['error'];
        }

        return new CheckResult(
            title:
                'Python Syntax Integrity',
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
