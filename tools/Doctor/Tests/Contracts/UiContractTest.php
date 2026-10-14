<?php

declare(strict_types=1);

require_once __DIR__ . '/../../Autoload.php';

use Tools\Doctor\Contracts\UiContract;

$temp = sys_get_temp_dir() . '/haya-doctor-ui-test-' . bin2hex(random_bytes(4));
mkdir($temp, 0777, true);

file_put_contents(
    $temp . '/template.html',
    '<html><head><meta name="viewport" content="width=device-width"></head><body><label for="email">Email</label><input id="email" type="text"></body></html>'
);

file_put_contents(
    $temp . '/styles.css',
    'button:hover {} button:focus {} button:disabled {} @media (min-width: 1px) { .btn {} } .btn { min-height: 44px; font-family: sans-serif; }'
);

file_put_contents(
    $temp . '/app.js',
    'console.log("ok");'
);

$result = UiContract::check($temp, [
    'templates' => ['template.html'],
    'stylesheets' => ['styles.css'],
    'scripts' => ['app.js'],
    'require_viewport' => true,
    'require_responsive_css' => true,
    'require_button_states' => true,
    'require_form_labels' => true,
    'require_consistent_fonts' => true,
    'require_spacing_tokens' => true,
    'require_touch_targets' => true,
]);

if ($result !== true) {
    throw new RuntimeException(
        'Expected UI check to pass with valid fixtures.'
    );
}

$score = UiContract::score($temp, [
    'templates' => ['template.html'],
    'stylesheets' => ['styles.css'],
    'scripts' => ['app.js'],
]);

if ($score['score'] !== 100) {
    throw new RuntimeException(
        'Expected UI score to be 100 when all assets exist.'
    );
}

$structure = UiContract::checkStructure($temp, [
    'templates' => ['template.html'],
    'required_landmarks' => ['html', 'body'],
]);

if ($structure !== true) {
    throw new RuntimeException(
        'Expected structure check to find required landmarks.'
    );
}

$responsive = UiContract::checkResponsive($temp, [
    'stylesheets' => ['styles.css'],
]);

if ($responsive !== true) {
    throw new RuntimeException(
        'Expected responsive check to detect @media query.'
    );
}

$accessible = UiContract::checkAccessibility($temp, [
    'templates' => ['template.html'],
]);

if ($accessible !== true) {
    throw new RuntimeException(
        'Expected accessibility check to pass.'
    );
}

array_map('unlink', glob($temp . '/*'));
rmdir($temp);

echo "[PASS] UiContract test.\n";
