<?php

declare(strict_types=1);

namespace Tools\Doctor\Contracts;

final class AdvertisedCapabilityContract
{
    /**
     * Compare capabilities advertised by one surface with capabilities
     * actually supported by another surface.
     *
     * No framework, project, route, UI, or domain assumptions are made.
     *
     * @param string[] $advertised
     * @param string[] $supported
     * @return array<string, mixed>
     */
    public static function check(
        array $advertised,
        array $supported
    ): array {
        $advertised = array_values(
            array_unique(
                array_filter(
                    $advertised,
                    static fn ($value): bool => is_string($value) && $value !== ''
                )
            )
        );

        $supported = array_values(
            array_unique(
                array_filter(
                    $supported,
                    static fn ($value): bool => is_string($value) && $value !== ''
                )
            )
        );

        $missing = array_values(
            array_diff($advertised, $supported)
        );

        return [
            'valid' => $missing === [],
            'advertised' => $advertised,
            'supported' => $supported,
            'missing' => $missing,
        ];
    }
}
