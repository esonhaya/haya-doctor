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
use Tools\Doctor\DTO\DoctorResult;
use Tools\Doctor\Engine\Doctor;
use Tools\Doctor\Metrics\DoctorMetricsPipeline;
use Tools\Doctor\Metrics\MetricRegistry;
use Tools\Doctor\Output\V2ConsoleWriter;
use Tools\Doctor\Snapshot\DoctorSnapshotBuilder;

MetricRegistry::reset();

$snapshot =
    (new DoctorSnapshotBuilder())
        ->build();

(new DoctorMetricsPipeline())
    ->analyze($snapshot);

DoctorSelfContext::setSnapshot(
    $snapshot
);

$result =
    new DoctorResult();

$checks =
    (new \Tools\Doctor\Registry\CheckRegistry())
        ->fromDirectories([
            $root . '/tools/Doctor/Self/Checks',
        ]);

foreach ($checks as $check) {
    $result->add(
        $check->run()
    );
}

(new V2ConsoleWriter())
    ->write($result);

exit(
    $result->failCount('DOCTOR') > 0
        ? 1
        : 0
);
