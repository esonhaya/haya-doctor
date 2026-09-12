<?php

declare(strict_types=1);

namespace Tools\Doctor\Output;

use Tools\Doctor\DTO\DoctorResult;

final class JsonReportWriter
{
    public function write(
        DoctorResult $result,
        string $path
    ): void {
        $v2 =
            (new V2ReportBuilder())
                ->build($result);

        $report = [
            'health' => $result->health(),

            'summary' => [
                'checks' => count($result->checks),
                'pass' => $result->passCount(),
                'warning' => $result->warningCount(),
                'fail' => $result->failCount(),
                'info' => $result->infoCount(),
                'skip' => $result->skipCount(),
                'findings' => $result->findingCount(),
            ],

            'diagnostics' => $v2['diagnostics'],
            'diagnosis' => $v2['diagnosis'],
            'fix_plan' => $v2['fix_plan'],
            'fix_plans' => $v2['fix_plans'],
            'priority_actions' => $v2['priority_actions'],

            'checks' => array_map(
                static function ($check): array {
                    return [
                        'title' => $check->title,
                        'id' => $check->id,
                        'status' => $check->status,
                        'summary' => $check->summary,
                        'details' => $check->details,
                        'recommendations' => $check->recommendations,
                        'score' => $check->score,
                        'scope' => $check->scope,
                        'metadata' => $check->metadata,
                        'findings' => $check->findings->toArray(),
                    ];
                },
                $result->checks
            ),

            'findings' =>
                $result->findings()->toArray(),

            'trend' => $result->trend,
        ];

        $directory = dirname($path);

        if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new \RuntimeException("Unable to create Doctor report directory: {$directory}");
        }

        $written = file_put_contents(
            $path,
            json_encode(
                $report,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
            )
        );

        if ($written === false) {
            throw new \RuntimeException("Unable to write Doctor report: {$path}");
        }
    }
}
