<?php

declare(strict_types=1);

namespace Tools\Doctor\Contracts\UI;

/**
 * Framework-independent interaction quality configuration.
 *
 * Project adapters describe expected interaction behavior.
 * Rendered browser infrastructure performs the actual inspection.
 */
final class UiInteractionContract
{
    public static function normalize(array $config): array
    {
        return [
            'loading' => $config['loading'] ?? [],
            'feedback' => $config['feedback'] ?? [],
            'disabled_during_action' =>
                $config['disabled_during_action'] ?? [],
            'aria_busy' => $config['aria_busy'] ?? [],
            'animation' => $config['animation'] ?? [],
            'reduced_motion' => $config['reduced_motion'] ?? true,
        ];
    }

    public static function checkStatic(
        string $root,
        array $config
    ): array {
        $results = [];
        $normalized = self::normalize($config);

        foreach ($normalized['animation'] as $rule) {
            $file = $root . '/' . ltrim(
                (string) ($rule['file'] ?? ''),
                '/'
            );

            if (!is_file($file)) {
                $results[] = [
                    'name' => 'animation-file',
                    'pass' => false,
                    'reason' => 'Animation target file missing',
                ];
                continue;
            }

            $source = file_get_contents($file) ?: '';

            $patterns = $rule['patterns'] ?? [];

            $matched = false;

            foreach ($patterns as $pattern) {
                if (
                    is_string($pattern) &&
                    $pattern !== '' &&
                    str_contains($source, $pattern)
                ) {
                    $matched = true;
                    break;
                }
            }

            $results[] = [
                'name' => 'animation',
                'pass' => $matched,
                'reason' => $matched
                    ? 'Animation implementation detected'
                    : 'Expected animation implementation missing',
            ];
        }

        return $results;
    }
}
