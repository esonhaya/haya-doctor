<?php

declare(strict_types=1);

namespace Tools\Doctor\Contracts\UI;

final class UiRenderedQualityContract
{
    public static function check(array $metrics, array $config = []): array
    {
        $findings = [];

        $elements = $metrics['elements'] ?? [];
        $hierarchy = $metrics['heading_levels'] ?? [];

        foreach ($elements as $name => $element) {
            if (!isset($element['rect'])) {
                continue;
            }

            $rect = $element['rect'];
            $container = $element['container_rect'] ?? null;

            if ($container !== null) {
                $leftGap = abs(($rect['left'] ?? 0) - ($container['left'] ?? 0));
                $rightGap = abs(
                    ($container['right'] ?? 0) - ($rect['right'] ?? 0)
                );

                $threshold = (float) ($config['edge_tolerance'] ?? 12);

                if ($leftGap > $threshold && $rightGap > $threshold) {
                    $findings[] = [
                        'type' => 'alignment',
                        'element' => $name,
                        'message' => 'Element is visually detached from its container edges',
                        'left_gap' => round($leftGap, 1),
                        'right_gap' => round($rightGap, 1),
                    ];
                }
            }
        }

        for ($i = 1, $count = count($hierarchy); $i < $count; $i++) {
            $previous = (int) $hierarchy[$i - 1];
            $current = (int) $hierarchy[$i];

            if ($current > $previous + 1) {
                $findings[] = [
                    'type' => 'hierarchy',
                    'message' => "Heading hierarchy skips from h{$previous} to h{$current}",
                    'from' => $previous,
                    'to' => $current,
                ];
            }
        }

        return [
            'status' => $findings === [] ? 'PASS' : 'WARN',
            'findings' => $findings,
            'count' => count($findings),
        ];
    }
}
