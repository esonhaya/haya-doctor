<?php

declare(strict_types=1);

namespace Tools\Doctor\Contracts;

final class DependencyContract
{
    /**
     * Verify that required files and executable commands exist.
     *
     * Definitions are intentionally generic so this can be reused by
     * unrelated projects.
     *
     * @param string[] $files
     * @param string[] $commands
     * @return array<string, mixed>
     */
    public static function check(
        array $files = [],
        array $commands = []
    ): array {
        $missingFiles = [];
        $missingCommands = [];

        foreach ($files as $file) {
            if (!is_string($file) || $file === '') {
                continue;
            }

            if (!is_file($file)) {
                $missingFiles[] = $file;
            }
        }

        foreach ($commands as $command) {
            if (!is_string($command) || $command === '') {
                continue;
            }

            $output = [];
            $status = 0;

            exec(
                'command -v ' . escapeshellarg($command) . ' 2>&1',
                $output,
                $status
            );

            if ($status !== 0) {
                $missingCommands[] = $command;
            }
        }

        return [
            'valid' => $missingFiles === [] && $missingCommands === [],
            'missing_files' => $missingFiles,
            'missing_commands' => $missingCommands,
        ];
    }
}
