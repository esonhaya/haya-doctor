<?php

declare(strict_types=1);

namespace Tools\Doctor\Engine;

use Tools\Doctor\Context\DoctorContext;
use Tools\Doctor\Context\DoctorSelfContext;
use Tools\Doctor\Contracts\CheckInterface;
use Tools\Doctor\DTO\DoctorResult;
use Tools\Doctor\Metrics\DoctorMetricsPipeline;
use Tools\Doctor\Metrics\MetricRegistry;
use Tools\Doctor\Metrics\MetricsPipeline;
use Tools\Doctor\Output\JsonReportWriter;
use Tools\Doctor\Output\V2ConsoleWriter;
use Tools\Doctor\Snapshot\DoctorSnapshotBuilder;
use Tools\Doctor\Snapshot\ProjectSnapshotBuilder;
use Tools\Doctor\Registry\CheckRegistry;

final class DoctorRunner
{
    public function __construct(
        private readonly ?CheckRegistry $registry = null,
        /** @var array<int,string>|null */
        private readonly ?array $checkDirectories = null,
        private readonly ?string $reportPath = null,
        private readonly ?CheckRunner $checkRunner = null,
    ) {
    }

    /**
     * @param array<int,CheckInterface>|null $checks
     */
    public function run(?array $checks = null): DoctorResult
    {
        MetricRegistry::reset();

        $projectSnapshot =
            (new ProjectSnapshotBuilder())
                ->build();

        (new MetricsPipeline())
            ->analyze(
                $projectSnapshot
            );

        DoctorContext::setSnapshot(
            $projectSnapshot
        );

        $doctorSnapshot =
            (new DoctorSnapshotBuilder())
                ->build();

        (new DoctorMetricsPipeline())
            ->analyze(
                $doctorSnapshot
            );

        DoctorSelfContext::setSnapshot(
            $doctorSnapshot
        );

        $checks ??= $this->checks();
        $result = ($this->checkRunner ?? new CheckRunner())
            ->run($checks);

        if ($this->reportPath !== null) {
            (new JsonReportWriter())->write($result, $this->reportPath);
        }

        (new V2ConsoleWriter())
            ->write($result);

        return $result;
    }

    /** @return array<int,CheckInterface> */
    private function checks(): array
    {
        $registry = $this->registry ?? new CheckRegistry();
        $directories = $this->checkDirectories;

        if ($directories === null) {
            $root = getcwd();
            if ($root === false) {
                throw new \RuntimeException('Unable to determine the Doctor project root.');
            }
            $directories = [$root . '/tools/Doctor/Self/Checks'];
        }

        return $registry->all($directories);
    }
}
