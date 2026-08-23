<?php

declare(strict_types=1);

namespace Tools\Doctor\Contracts;

final class UiContract
{
    /**
     * @param array{
     *   templates?: string[],
     *   stylesheets?: string[],
     *   scripts?: string[],
     *   require_viewport?: bool,
     *   require_responsive_css?: bool,
     *   require_button_states?: bool,
     *   require_form_labels?: bool,
     *   require_consistent_fonts?: bool,
     *   require_spacing_tokens?: bool,
     *   require_touch_targets?: bool
     * } $config
     */
    public static function check(string $root, array $config): bool
    {
        $ok = true;

        $templates = $config['templates'] ?? [];
        $stylesheets = $config['stylesheets'] ?? [];
        $scripts = $config['scripts'] ?? [];

        foreach ($templates as $relative) {
            $path = $root . '/' . ltrim($relative, '/');

            if (!is_file($path)) {
                $ok = false;
                continue;
            }

            $html = file_get_contents($path);

            if (($config['require_viewport'] ?? true)
                && stripos($html, 'name="viewport"') === false
                && stripos($html, "name='viewport'") === false) {
                $ok = false;
            }

            if (($config['require_form_labels'] ?? true)) {
                preg_match_all(
                    '/<input\b([^>]*)>/i',
                    $html,
                    $inputs
                );

                foreach ($inputs[1] as $attributes) {
                    if (preg_match('/type=["\']hidden["\']/i', $attributes)) {
                        continue;
                    }

                    if (!preg_match('/id=["\']([^"\']+)["\']/i', $attributes, $id)) {
                        continue;
                    }

                    $label = preg_quote($id[1], '/');

                    if (!preg_match(
                        '/<label\b[^>]*for=["\']' . $label . '["\']/i',
                        $html
                    )) {
                        $ok = false;
                    }
                }
            }
        }

        foreach ($stylesheets as $relative) {
            $path = $root . '/' . ltrim($relative, '/');

            if (!is_file($path)) {
                $ok = false;
                continue;
            }

            $css = file_get_contents($path);

            if (($config['require_responsive_css'] ?? true)
                && stripos($css, '@media') === false) {
                $ok = false;
            }

            if (($config['require_button_states'] ?? true)) {
                foreach ([':hover', ':focus', ':disabled'] as $state) {
                    if (stripos($css, $state) === false) {
                        $ok = false;
                    }
                }
            }

            if (($config['require_consistent_fonts'] ?? true)) {
                preg_match_all(
                    '/font-family\s*:\s*([^;{}]+)/i',
                    $css,
                    $fonts
                );

                $families = array_unique(
                    array_map(
                        static fn(string $font): string =>
                            strtolower(trim(preg_replace('/\s+/', ' ', $font))),
                        $fonts[1]
                    )
                );

                if (count($families) > 4) {
                    $ok = false;
                }
            }

            if (($config['require_spacing_tokens'] ?? true)) {
                $spacingDeclarations = preg_match_all(
                    '/(?:margin|padding|gap)\s*:\s*([0-9.]+)(px|rem|em)/i',
                    $css,
                    $matches
                );

                if ($spacingDeclarations > 20) {
                    $ok = false;
                }
            }

            if (($config['require_touch_targets'] ?? true)
                && stripos($css, 'min-height') === false
                && stripos($css, 'min-width') === false) {
                $ok = false;
            }
        }

        foreach ($scripts as $relative) {
            $path = $root . '/' . ltrim($relative, '/');

            if (!is_file($path)) {
                $ok = false;
            }
        }

        return $ok;
    }


    public static function score(
        string $root,
        array $config
    ): array {
        $checks = [];
        $total = 0;
        $passed = 0;

        $structure = self::checkStructure($root, $config);
        $checks['structure'] = $structure;

        $total++;
        if ($structure) {
            $passed++;
        }

        foreach ([
            'stylesheets' => 'styles',
            'scripts' => 'scripts',
            'templates' => 'templates',
        ] as $configKey => $category) {
            foreach (($config[$configKey] ?? []) as $relative) {
                $total++;

                $exists = is_file(
                    $root . DIRECTORY_SEPARATOR . $relative
                );

                $checks[$category . ':' . $relative] = $exists;

                if ($exists) {
                    $passed++;
                }
            }
        }

        return [
            'score' => $total
                ? (int) round(($passed / $total) * 100)
                : 100,
            'passed' => $passed,
            'total' => $total,
            'checks' => $checks,
        ];
    }


    public static function checkStructure(
        string $root,
        array $config
    ): bool {
        foreach (($config['templates'] ?? []) as $template) {
            $path = $root . DIRECTORY_SEPARATOR . $template;

            if (!is_file($path)) {
                return false;
            }

            $html = file_get_contents($path);

            if ($html === false) {
                return false;
            }

            $requirements =
                $config['template_landmarks'][$template]
                ?? $config['required_landmarks']
                ?? [];

            foreach ($requirements as $landmark => $required) {
                if (is_int($landmark)) {
                    $landmark = $required;
                    $required = true;
                }

                if (!$required) {
                    continue;
                }

                if (
                    stripos(
                        $html,
                        '<' . strtolower($landmark)
                    ) === false
                ) {
                    return false;
                }
            }
        }

        return true;
    }


    public static function checkResponsive(
        string $root,
        array $config
    ): bool {
        $stylesheets = $config['stylesheets'] ?? [];

        foreach ($stylesheets as $stylesheet) {
            $path = $root . DIRECTORY_SEPARATOR . $stylesheet;

            if (!is_file($path)) {
                return false;
            }

            $css = file_get_contents($path);

            if ($css === false) {
                return false;
            }

            if (
                stripos($css, '@media') === false
                && !($config['allow_no_media_query'] ?? false)
            ) {
                return false;
            }
        }

        return true;
    }

    public static function checkAccessibility(
        string $root,
        array $config
    ): bool {
        foreach (($config['templates'] ?? []) as $template) {
            $path = $root . DIRECTORY_SEPARATOR . $template;
            $html = file_get_contents($path);

            if ($html === false) {
                return false;
            }

            if (
                preg_match('/<img\b(?![^>]*\balt\s*=)/i', $html)
            ) {
                return false;
            }

            if (
                preg_match(
                    '/<button\b[^>]*>\s*<\/button>/i',
                    $html
                )
            ) {
                return false;
            }
        }

        return true;
    }


}
