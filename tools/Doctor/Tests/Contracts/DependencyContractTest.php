<?php

declare(strict_types=1);

require_once __DIR__ . '/../../Autoload.php';

use Tools\Doctor\Contracts\DependencyContract;

$temp = sys_get_temp_dir() . '/haya-doctor-dependency-test-' . bin2hex(random_bytes(4));
mkdir($temp, 0777, true);

$existingFile = $temp . '/existing.txt';
touch($existingFile);

$result = DependencyContract::check(
    [$existingFile, $temp . '/missing.txt'],
    ['php']
);

if ($result['valid'] !== false) {
    throw new RuntimeException(
        'Expected invalid result when a file is missing.'
    );
}

if ($result['missing_files'] !== [$temp . '/missing.txt']) {
    throw new RuntimeException(
        'Expected missing file list to contain the missing file.'
    );
}

if ($result['missing_commands'] !== []) {
    throw new RuntimeException(
        'Expected php command to be available.'
    );
}

$result = DependencyContract::check(
    [$existingFile],
    ['definitely-not-a-real-command-' . bin2hex(random_bytes(4))]
);

if ($result['valid'] !== false) {
    throw new RuntimeException(
        'Expected invalid result when a command is missing.'
    );
}

if ($result['missing_commands'] === []) {
    throw new RuntimeException(
        'Expected missing command list to contain the missing command.'
    );
}

$result = DependencyContract::check(
    [$existingFile],
    ['php']
);

if ($result['valid'] !== true) {
    throw new RuntimeException(
        'Expected valid result when file and command exist.'
    );
}

unlink($existingFile);
rmdir($temp);

echo "[PASS] DependencyContract test.\n";
