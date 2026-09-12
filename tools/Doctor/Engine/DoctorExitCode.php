<?php

declare(strict_types=1);

namespace Tools\Doctor\Engine;

use Tools\Doctor\DTO\DoctorResult;
use Throwable;

final class DoctorExitCode
{
    public static function forResult(DoctorResult $result): int
    {
        return $result->hasFailures() ? 1 : 0;
    }

    public static function forExecutionFailure(Throwable $exception): int
    {
        return 2;
    }
}
