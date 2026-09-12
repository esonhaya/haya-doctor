<?php

declare(strict_types=1);

namespace Tools\Doctor\Checks;

use Tools\Doctor\Contracts\CheckIdentityInterface;
use Tools\Doctor\Contracts\CheckInterface;
use Tools\Doctor\DTO\CheckResult;
use Tools\Doctor\DTO\CheckStatus;

final class PhpRuntimeCheck implements CheckInterface, CheckIdentityInterface
{
    public function __construct(private readonly string $minimumVersion = '8.2.0')
    {
    }

    public function id(): string
    {
        return 'runtime.php';
    }

    public function run(): CheckResult
    {
        $passes = version_compare(PHP_VERSION, $this->minimumVersion, '>=');

        return new CheckResult(
            title: 'PHP Runtime',
            status: $passes ? CheckStatus::PASS : CheckStatus::FAIL,
            summary: $passes
                ? sprintf('PHP %s satisfies the minimum version.', PHP_VERSION)
                : sprintf('PHP %s is below the required version %s.', PHP_VERSION, $this->minimumVersion),
            details: [
                'PHP version: ' . PHP_VERSION,
                'PHP SAPI: ' . PHP_SAPI,
            ],
            recommendations: $passes ? [] : ['Install or select a supported PHP runtime.'],
            score: $passes ? 100 : 0,
            scope: 'DOCTOR',
            id: $this->id(),
            metadata: [
                'minimum_version' => $this->minimumVersion,
                'sapi' => PHP_SAPI,
            ],
        );
    }

    public function category(): string
    {
        return 'runtime';
    }

    public function priority(): int
    {
        return 5;
    }
}
