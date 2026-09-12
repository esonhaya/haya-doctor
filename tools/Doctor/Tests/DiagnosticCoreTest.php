<?php

declare(strict_types=1);

require_once __DIR__ . '/../Autoload.php';

use Tools\Doctor\Checks\PhpRuntimeCheck;
use Tools\Doctor\Contracts\CheckIdentityInterface;
use Tools\Doctor\Contracts\CheckInterface;
use Tools\Doctor\DTO\CheckResult;
use Tools\Doctor\DTO\CheckStatus;
use Tools\Doctor\DTO\DoctorResult;
use Tools\Doctor\Engine\CheckRunner;
use Tools\Doctor\Engine\DoctorExitCode;
use Tools\Doctor\Output\JsonRenderer;
use Tools\Doctor\Registry\CheckRegistry;

final class DiagnosticPassFixture implements CheckInterface, CheckIdentityInterface
{
    public function id(): string { return 'fixture.pass'; }
    public function run(): CheckResult { return new CheckResult('Pass', CheckStatus::PASS, metadata: ['fixture' => true]); }
    public function category(): string { return 'fixture'; }
    public function priority(): int { return 20; }
}

final class DiagnosticWarningFixture implements CheckInterface, CheckIdentityInterface
{
    public function id(): string { return 'fixture.warn'; }
    public function run(): CheckResult { return new CheckResult('Warn', 'WARNING'); }
    public function category(): string { return 'fixture'; }
    public function priority(): int { return 10; }
}

final class DiagnosticSkipFixture implements CheckInterface, CheckIdentityInterface
{
    public function id(): string { return 'fixture.skip'; }
    public function run(): CheckResult { return new CheckResult('Skip', 'INFO'); }
    public function category(): string { return 'fixture'; }
    public function priority(): int { return 30; }
}

final class DiagnosticFailureFixture implements CheckInterface, CheckIdentityInterface
{
    public function id(): string { return 'fixture.failure'; }
    public function run(): CheckResult { throw new RuntimeException('fixture failure'); }
    public function category(): string { return 'fixture'; }
    public function priority(): int { return 40; }
}

final class DiagnosticAlphaFixture implements CheckInterface, CheckIdentityInterface
{
    public function id(): string { return 'fixture.alpha'; }
    public function run(): CheckResult { return new CheckResult('Alpha', CheckStatus::PASS); }
    public function category(): string { return 'fixture'; }
    public function priority(): int { return 5; }
}

final class DiagnosticZetaFixture implements CheckInterface, CheckIdentityInterface
{
    public function id(): string { return 'fixture.zeta'; }
    public function run(): CheckResult { return new CheckResult('Zeta', CheckStatus::PASS); }
    public function category(): string { return 'fixture'; }
    public function priority(): int { return 5; }
}

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$runner = new CheckRunner();
$result = $runner->run([
    new DiagnosticPassFixture(),
    new DiagnosticWarningFixture(),
    new DiagnosticSkipFixture(),
    new DiagnosticFailureFixture(),
]);

$assert($result->passCount() === 1, 'PASS count is incorrect.');
$assert($result->warningCount() === 1, 'WARN normalization/count is incorrect.');
$assert($result->skipCount() === 1, 'SKIP normalization/count is incorrect.');
$assert($result->failCount() === 1, 'FAIL count is incorrect.');
$assert($result->checks[0]->id === 'fixture.pass', 'Explicit check ID was not preserved.');
$assert($result->checks[0]->metadata['fixture'] === true, 'Structured metadata was not preserved.');
$assert($result->checks[3]->metadata['exception'] === RuntimeException::class, 'Check exception metadata is missing.');
$assert(DoctorExitCode::forResult(new DoctorResult()) === 0, 'Exit code 0 contract failed.');
$assert(DoctorExitCode::forResult($result) === 1, 'Exit code 1 contract failed.');
$assert(DoctorExitCode::forExecutionFailure(new RuntimeException()) === 2, 'Exit code 2 contract failed.');

$registry = new CheckRegistry();
$ordered = $registry->fromChecks([
    new DiagnosticZetaFixture(),
    new DiagnosticAlphaFixture(),
]);
$assert(array_map(static fn(CheckInterface $check): string => CheckRegistry::idFor($check), $ordered) === ['fixture.alpha', 'fixture.zeta'], 'Registry ordering is not deterministic.');

$duplicateFailed = false;
try {
    $registry->register(new DiagnosticAlphaFixture());
} catch (RuntimeException) {
    $duplicateFailed = true;
}
$assert($duplicateFailed, 'Duplicate check IDs were not rejected.');

$json = json_decode((new JsonRenderer())->render($result), true, 512, JSON_THROW_ON_ERROR);
$assert($json['checks'][0]['id'] === 'fixture.pass', 'JSON output omitted check ID.');
$assert($json['checks'][0]['metadata']['fixture'] === true, 'JSON output omitted metadata.');
$assert($json['skip'] === 1, 'JSON output omitted SKIP count.');
$assert((new PhpRuntimeCheck())->id() === 'runtime.php', 'Generic PHP runtime check ID is unstable.');

echo "[PASS] Generic diagnostic core regression suite." . PHP_EOL;
