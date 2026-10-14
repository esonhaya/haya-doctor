<?php

declare(strict_types=1);

namespace Tools\Doctor\Contracts\UI;

/**
 * Generic static UI quality heuristics.
 *
 * These checks deliberately avoid framework-specific assumptions.
 * Rendered geometry belongs to the optional browser contract.
 */
final class UiHeuristicsContract
{
    public static function score(string $root, array $config): array
    {
        $checks = [];
        $passed = 0;

        $check = static function (
            string $name,
            bool $condition,
            string $reason = ''
        ) use (&$checks, &$passed): void {
            $checks[$name] = [
                'pass' => $condition,
                'reason' => $condition ? '' : $reason,
            ];

            if ($condition) {
                $passed++;
            }
        };

        $templates = $config['templates'] ?? [];
        $stylesheets = $config['stylesheets'] ?? [];

        $html = '';
        foreach ($templates as $relative) {
            $path = $root . '/' . ltrim((string) $relative, '/');

            if (is_file($path)) {
                $html .= "\n" . file_get_contents($path);
            }
        }

        $css = '';
        foreach ($stylesheets as $relative) {
            $path = $root . '/' . ltrim((string) $relative, '/');

            if (is_file($path)) {
                $css .= "\n" . file_get_contents($path);
            }
        }

        /*
         * Semantic structure.
         */
        $check(
            'semantic.main',
            $html !== '' && preg_match('/<main\b/i', $html) === 1,
            'No <main> landmark detected'
        );

        $check(
            'semantic.heading',
            $html !== '' && preg_match('/<h1\b/i', $html) === 1,
            'No primary heading detected'
        );

        $check(
            'semantic.labels',
            $html !== '' &&
            !preg_match(
                '/<input\b(?![^>]*\baria-label=)(?![^>]*\bid=)[^>]*>/i',
                $html
            ),
            'Unlabelled input detected'
        );

        /*
         * Interaction and component consistency.
         */
        $buttonCount = preg_match_all('/<(?:button|input\b[^>]*type=["\'](?:submit|button)["\'])/i', $html);
        $buttonCount = $buttonCount === false ? 0 : $buttonCount;

        $check(
            'interaction.controls',
            $buttonCount === 0 || preg_match('/button\s*\{|button[^{]*\{/i', $css) === 1,
            'Interactive controls have no shared button styling'
        );

        $check(
            'interaction.focus',
            preg_match('/focus-visible|:focus\b/i', $css) === 1,
            'No visible focus styling detected'
        );

        $check(
            'interaction.disabled',
            $buttonCount === 0 ||
            preg_match('/disabled|:disabled/i', $css) === 1,
            'No disabled-state styling detected'
        );

        /*
         * Responsive foundation.
         */
        $check(
            'responsive.viewport',
            preg_match('/@media\s*\(/i', $css) === 1,
            'No responsive media query detected'
        );

        $check(
            'responsive.box-sizing',
            preg_match('/box-sizing\s*:\s*border-box/i', $css) === 1,
            'Global border-box sizing not detected'
        );

        /*
         * Typography system.
         */
        $fontSizes = [];
        if (preg_match_all(
            '/font-size\s*:\s*([0-9.]+)(px|rem|em|%)?/i',
            $css,
            $matches
        )) {
            foreach ($matches[1] as $index => $value) {
                $unit = $matches[2][$index] ?? '';
                $fontSizes[] = (float) $value . $unit;
            }
        }

        $check(
            'typography.system',
            count(array_unique($fontSizes)) >= 2,
            'Typography has insufficient size hierarchy'
        );

        $check(
            'typography.line-height',
            preg_match('/line-height\s*:/i', $css) === 1,
            'No explicit line-height system detected'
        );

        /*
         * Spacing system.
         */
        $spacingValues = [];
        if (preg_match_all(
            '/(?:margin|padding|gap)\s*:[^;{}]*?([0-9.]+)(px|rem|em)/i',
            $css,
            $matches
        )) {
            foreach ($matches[1] as $index => $value) {
                $spacingValues[] = $value . ($matches[2][$index] ?? '');
            }
        }

        $uniqueSpacing = array_values(array_unique($spacingValues));

        $check(
            'spacing.system',
            count($uniqueSpacing) >= 3,
            'Insufficient spacing scale detected'
        );

        /*
         * Component system.
         */
        $check(
            'components.cards',
            !preg_match('/<div[^>]*class=["\'][^"\']*\bcard\b/i', $html) ||
            preg_match('/\.card\b/i', $css) === 1,
            'Card markup exists without shared card styling'
        );

        $check(
            'components.buttons',
            $buttonCount < 2 ||
            preg_match_all('/button\s*\{|button[^{]*\{/i', $css) >= 1,
            'Buttons do not appear to share a component style'
        );

        /*
         * Content readability.
         */
        $check(
            'content.readability',
            $html === '' ||
            !preg_match('/<p[^>]*>\s*[^<]{180,}/is', $html),
            'Long unbroken paragraph detected'
        );

        /*
         * Alignment sanity from source-level declarations.
         *
         * This does not claim to know rendered alignment. The browser
         * contract handles actual computed geometry.
         */
        $alignmentDeclarations = [];
        if (preg_match_all(
            '/text-align\s*:\s*(left|center|right|justify)/i',
            $css,
            $matches
        )) {
            $alignmentDeclarations = array_map('strtolower', $matches[1]);
        }

        $check(
            'alignment.explicit',
            empty($alignmentDeclarations) ||
            count(array_unique($alignmentDeclarations)) <= 4,
            'Unexpected alignment declaration pattern'
        );

        /*
         * Accessibility baseline.
         */
        $check(
            'accessibility.focus',
            preg_match('/focus-visible|:focus\b/i', $css) === 1,
            'Focus indicator missing'
        );

        $check(
            'accessibility.touch',
            $buttonCount === 0 ||
            preg_match(
                '/min-(?:height|width)\s*:\s*(4[0-9]|[5-9][0-9])px/i',
                $css
            ) === 1 ||
            preg_match(
                '/padding\s*:[^;{}]*\b(1[2-9]|[2-9][0-9])px/i',
                $css
            ) === 1,
            'No source-level touch-target safeguard detected'
        );

        $total = count($checks);
        $score = $total === 0
            ? 100
            : (int) round(($passed / $total) * 100);

        return [
            'score' => $score,
            'passed' => $passed,
            'total' => $total,
            'checks' => $checks,
        ];
    }

    public static function check(string $root, array $config): bool
    {
        $result = self::score($root, $config);

        return ($result['score'] ?? 0) >=
            (int) ($config['minimum_score'] ?? 80);
    }
}
