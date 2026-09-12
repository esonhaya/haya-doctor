#!/usr/bin/env php
<?php

declare(strict_types=1);

$root =
    dirname(__DIR__);

chdir($root);

require_once
    $root
    . '/tools/Doctor/Autoload.php';

use Tools\Doctor\Context\DoctorSelfContext;
use Tools\Doctor\Checks\PhpRuntimeCheck;
use Tools\Doctor\Engine\CheckRunner;
use Tools\Doctor\Engine\DoctorExitCode;
use Tools\Doctor\Metrics\DoctorMetricsPipeline;
use Tools\Doctor\Metrics\MetricRegistry;
use Tools\Doctor\Output\V2ConsoleWriter;
use Tools\Doctor\Registry\CheckRegistry;
use Tools\Doctor\Snapshot\DoctorSnapshotBuilder;

try {
    MetricRegistry::reset();

    $snapshot =
        (new DoctorSnapshotBuilder())
            ->build();

    (new DoctorMetricsPipeline())
        ->analyze($snapshot);

    DoctorSelfContext::setSnapshot(
        $snapshot
    );

    $registry = new CheckRegistry();
    $registry->fromDirectories([
        $root . '/tools/Doctor/Self/Checks',
    ]);
    $registry->register(new PhpRuntimeCheck());
    $checks = $registry->all();

    $result =
        (new CheckRunner())
            ->run($checks, 'DOCTOR');

    (new V2ConsoleWriter())
        ->write($result);

    exit(DoctorExitCode::forResult($result));
} catch (Throwable $exception) {
    fwrite(
        STDERR,
        '[DOCTOR ERROR] ' . $exception->getMessage() . PHP_EOL
    );
    exit(DoctorExitCode::forExecutionFailure($exception));
}
