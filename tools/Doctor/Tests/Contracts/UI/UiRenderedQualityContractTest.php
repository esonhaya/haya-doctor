<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../Autoload.php';

use Tools\Doctor\Contracts\UI\UiRenderedQualityContract;

$result = UiRenderedQualityContract::check(
    [
        'elements' => [
            'header' => [
                'rect' => ['left' => 0, 'right' => 100],
                'container_rect' => ['left' => 0, 'right' => 100],
            ],
        ],
        'heading_levels' => [1, 2, 3],
    ],
    ['edge_tolerance' => 12]
);

if ($result['status'] !== 'PASS') {
    throw new RuntimeException(
        'Expected PASS for aligned elements and valid heading hierarchy.'
    );
}

if ($result['count'] !== 0) {
    throw new RuntimeException(
        'Expected no findings for valid metrics.'
    );
}

$result = UiRenderedQualityContract::check(
    [
        'elements' => [
            'header' => [
                'rect' => ['left' => 20, 'right' => 80],
                'container_rect' => ['left' => 0, 'right' => 100],
            ],
        ],
        'heading_levels' => [1, 3],
    ],
    ['edge_tolerance' => 12]
);

if ($result['status'] !== 'WARN') {
    throw new RuntimeException(
        'Expected WARN for detached element and skipped heading level.'
    );
}

if ($result['count'] !== 2) {
    throw new RuntimeException(
        'Expected two findings.'
    );
}

echo "[PASS] UiRenderedQualityContract test.\n";
