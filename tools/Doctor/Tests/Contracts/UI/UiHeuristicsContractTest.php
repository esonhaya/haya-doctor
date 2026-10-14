<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../Autoload.php';

use Tools\Doctor\Contracts\UI\UiHeuristicsContract;

$temp = sys_get_temp_dir() . '/haya-doctor-ui-heuristics-test-' . bin2hex(random_bytes(4));
mkdir($temp, 0777, true);

file_put_contents(
    $temp . '/page.html',
    '<main><h1>Title</h1><p>Short.</p></main>'
);

file_put_contents(
    $temp . '/styles.css',
    'button {} button:hover {} button:focus {} button:disabled {} .btn { min-height: 44px; } @media (min-width: 1px) { .btn {} } * { box-sizing: border-box; font-size: 14px; font-size: 16px; line-height: 1.5; margin: 4px; padding: 8px; gap: 12px; }'
);

$score = UiHeuristicsContract::score($temp, [
    'templates' => ['page.html'],
    'stylesheets' => ['styles.css'],
]);

if ($score['score'] < 80) {
    throw new RuntimeException(
        'Expected heuristic score to meet default minimum.'
    );
}

if ($score['passed'] <= 0) {
    throw new RuntimeException(
        'Expected at least one heuristic to pass.'
    );
}

$passes = UiHeuristicsContract::check($temp, [
    'templates' => ['page.html'],
    'stylesheets' => ['styles.css'],
    'minimum_score' => 80,
]);

if ($passes !== true) {
    throw new RuntimeException(
        'Expected heuristic check to pass.'
    );
}

array_map('unlink', glob($temp . '/*'));
rmdir($temp);

echo "[PASS] UiHeuristicsContract test.\n";
