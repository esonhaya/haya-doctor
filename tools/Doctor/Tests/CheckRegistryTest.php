<?php

declare(strict_types=1);

require_once __DIR__ . '/../Autoload.php';

use Tools\Doctor\Contracts\CheckInterface;
use Tools\Doctor\DTO\CheckResult;
use Tools\Doctor\Registry\CheckRegistry;

$directory =
    sys_get_temp_dir()
    . '/boardprep-doctor-registry-test-'
    . bin2hex(random_bytes(4));

mkdir(
    $directory,
    0777,
    true
);

$fixture =
    <<<'FIXTURE'
<?php

declare(strict_types=1);

namespace RegistryFixture;

use Tools\Doctor\Contracts\CheckInterface;
use Tools\Doctor\DTO\CheckResult;

final class RegistryFixtureCheck
    implements CheckInterface
{
    public function run(): CheckResult
    {
        return new CheckResult(
            title: 'Registry Fixture',
            status: 'PASS',
            summary: 'fixture',
        );
    }

    public function category(): string
    {
        return 'test';
    }

    public function priority(): int
    {
        return 10;
    }
}
FIXTURE;

file_put_contents(
    $directory . '/RegistryFixtureCheck.php',
    $fixture
);

try {
    $registry =
        new CheckRegistry();

    $checks =
        $registry->fromDirectories([
            $directory,
        ]);

    if (count($checks) !== 1) {
        throw new RuntimeException(
            'Expected exactly one registered check; got '
            . count($checks)
        );
    }

    if (
        !$checks[0] instanceof CheckInterface
    ) {
        throw new RuntimeException(
            'Registered object does not implement CheckInterface.'
        );
    }

    $result =
        $checks[0]->run();

    if ($result->title !== 'Registry Fixture') {
        throw new RuntimeException(
            'Unexpected registered check result title.'
        );
    }

    echo "[PASS] Generic CheckRegistry regression suite."
        . PHP_EOL;
} finally {
    if (
        is_file(
            $directory
            . '/RegistryFixtureCheck.php'
        )
    ) {
        unlink(
            $directory
            . '/RegistryFixtureCheck.php'
        );
    }

    if (is_dir($directory)) {
        rmdir($directory);
    }
}
