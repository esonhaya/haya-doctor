<?php

declare(strict_types=1);

namespace Tools\Doctor\Engine;

use Throwable;
use Tools\Doctor\Contracts\CheckIdentityInterface;
use Tools\Doctor\Contracts\CheckInterface;
use Tools\Doctor\DTO\CheckResult;
use Tools\Doctor\DTO\CheckStatus;
use Tools\Doctor\DTO\DoctorResult;
use Tools\Doctor\Metrics\MetricRegistry;
use Tools\Doctor\Registry\CheckRegistry;

final class CheckRunner
{
    /**
     * Execute supplied checks in their provided deterministic order.
     * A failing check is isolated and represented as FAIL; runner errors
     * outside a check remain the responsibility of the CLI boundary.
     *
     * @param iterable<CheckInterface> $checks
     */
    public function run(iterable $checks, string $failureScope = 'PROJECT'): DoctorResult
    {
        $result = new DoctorResult();
        $seenIds = [];

        foreach ($checks as $check) {
            $checkId = $check instanceof CheckIdentityInterface
                ? $check->id()
                : CheckRegistry::idFor($check);
            $checkId = trim($checkId);

            if ($checkId === '') {
                throw new \RuntimeException('Doctor check IDs cannot be empty.');
            }

            if (isset($seenIds[$checkId])) {
                throw new \RuntimeException(sprintf('Duplicate Doctor check ID "%s".', $checkId));
            }
            $seenIds[$checkId] = true;

            try {
                $checkResult = $check->run();
                if (!$checkResult instanceof CheckResult) {
                    throw new \RuntimeException(sprintf('Doctor check "%s" returned an invalid result.', $checkId));
                }
                $checkResult->id = $checkId;
                $checkResult->metadata = array_merge(
                    ['check_class' => $check::class],
                    $checkResult->metadata,
                );
            } catch (Throwable $exception) {
                $checkResult = new CheckResult(
                    title: $check::class,
                    status: CheckStatus::FAIL,
                    summary: 'Doctor check execution failed.',
                    details: [$exception->getMessage()],
                    recommendations: ['Fix the check failure and run Doctor again.'],
                    score: 0,
                    scope: $failureScope,
                    id: $checkId,
                    metadata: [
                        'check_class' => $check::class,
                        'exception' => $exception::class,
                    ],
                );
            }

            MetricRegistry::set($checkId, $checkResult);
            $result->add($checkResult);
        }

        return $result;
    }
}
