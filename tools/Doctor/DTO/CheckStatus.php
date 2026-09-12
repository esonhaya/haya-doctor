<?php

declare(strict_types=1);

namespace Tools\Doctor\DTO;

use InvalidArgumentException;

final class CheckStatus
{
    public const PASS = 'PASS';
    public const WARN = 'WARN';
    public const FAIL = 'FAIL';
    public const SKIP = 'SKIP';

    public static function normalize(string $status): string
    {
        return match (strtoupper(trim($status))) {
            self::PASS => self::PASS,
            self::WARN, 'WARNING' => self::WARN,
            self::FAIL => self::FAIL,
            self::SKIP, 'INFO' => self::SKIP,
            default => throw new InvalidArgumentException(sprintf('Unsupported Doctor check status "%s".', $status)),
        };
    }

    public static function isFailure(string $status): bool
    {
        return self::normalize($status) === self::FAIL;
    }
}
