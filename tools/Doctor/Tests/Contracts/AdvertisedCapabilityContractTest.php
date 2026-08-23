<?php

declare(strict_types=1);

require_once __DIR__ . '/../../Autoload.php';

use Tools\Doctor\Contracts\AdvertisedCapabilityContract;

$result = AdvertisedCapabilityContract::check(
    ['a', 'b', 'c'],
    ['a', 'b', 'c']
);

if ($result['valid'] !== true) {
    throw new RuntimeException(
        'Expected valid result when all capabilities are supported.'
    );
}

if ($result['missing'] !== []) {
    throw new RuntimeException(
        'Expected no missing capabilities.'
    );
}

$result = AdvertisedCapabilityContract::check(
    ['a', 'b', 'c'],
    ['a', 'b']
);

if ($result['valid'] !== false) {
    throw new RuntimeException(
        'Expected invalid result when a capability is missing.'
    );
}

if ($result['missing'] !== ['c']) {
    throw new RuntimeException(
        'Expected missing capability to be [c].'
    );
}

$result = AdvertisedCapabilityContract::check(
    ['a', '', 'b', 123, 'c', 'a'],
    ['c', 'b', 'a']
);

if ($result['valid'] !== true) {
    throw new RuntimeException(
        'Expected valid result after filtering duplicates and non-strings.'
    );
}

if ($result['advertised'] !== ['a', 'b', 'c']) {
    throw new RuntimeException(
        'Expected advertised list to be cleaned and deduplicated.'
    );
}

echo "[PASS] AdvertisedCapabilityContract test.\n";
