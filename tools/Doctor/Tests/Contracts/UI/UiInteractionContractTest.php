<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../Autoload.php';

use Tools\Doctor\Contracts\UI\UiInteractionContract;

$normalized = UiInteractionContract::normalize([
    'loading' => ['save' => true],
    'reduced_motion' => false,
]);

if ($normalized['loading'] !== ['save' => true]) {
    throw new RuntimeException(
        'Expected loading configuration to be preserved.'
    );
}

if ($normalized['reduced_motion'] !== false) {
    throw new RuntimeException(
        'Expected reduced_motion configuration to be preserved.'
    );
}

if ($normalized['feedback'] !== []) {
    throw new RuntimeException(
        'Expected default feedback to be empty array.'
    );
}

$temp = sys_get_temp_dir() . '/haya-doctor-ui-interaction-test-' . bin2hex(random_bytes(4));
mkdir($temp, 0777, true);

file_put_contents(
    $temp . '/animation.css',
    '@keyframes fadeIn {}'
);

$results = UiInteractionContract::checkStatic($temp, [
    'animation' => [
        [
            'file' => 'animation.css',
            'patterns' => ['@keyframes fadeIn'],
        ],
    ],
]);

if (count($results) !== 1) {
    throw new RuntimeException(
        'Expected one animation check result.'
    );
}

if ($results[0]['pass'] !== true) {
    throw new RuntimeException(
        'Expected animation pattern to be detected.'
    );
}

$results = UiInteractionContract::checkStatic($temp, [
    'animation' => [
        [
            'file' => 'missing.css',
            'patterns' => ['@keyframes fadeIn'],
        ],
    ],
]);

if ($results[0]['pass'] !== false) {
    throw new RuntimeException(
        'Expected missing animation file to fail.'
    );
}

array_map('unlink', glob($temp . '/*'));
rmdir($temp);

echo "[PASS] UiInteractionContract test.\n";
