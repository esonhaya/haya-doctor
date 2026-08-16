<?php

declare(strict_types=1);

namespace Tools\Doctor\Python;

final class PythonRuntime
{
    public const DEFAULT_TIMEOUT = 5;

    public static function executable(): ?string
    {
        foreach (['python3', 'python'] as $candidate) {
            $command = 'command -v '
                . escapeshellarg($candidate)
                . ' 2>/dev/null';

            $path = trim(
                (string) shell_exec($command)
            );

            if ($path !== '') {
                return $path;
            }
        }

        return null;
    }

    public static function version(
        ?string $python = null
    ): ?string {
        $python ??= self::executable();

        if ($python === null) {
            return null;
        }

        $result = self::run(
            $python,
            ['--version'],
            self::DEFAULT_TIMEOUT
        );

        if (
            !$result['ok']
            || $result['timeout']
            || $result['output'] === ''
        ) {
            return null;
        }

        return trim($result['output']);
    }

    public static function available(): bool
    {
        return self::executable() !== null;
    }

    /**
     * @param string[] $arguments
     *
     * @return array{
     *     ok: bool,
     *     timeout: bool,
     *     exitCode: int,
     *     output: string
     * }
     */
    public static function run(
        string $python,
        array $arguments,
        int $timeout = self::DEFAULT_TIMEOUT
    ): array {
        $command = array_merge(
            [$python],
            array_map(
                static fn(mixed $argument): string =>
                    (string) $argument,
                $arguments
            )
        );

        $descriptors = [
            0 => [
                'pipe',
                'r',
            ],
            1 => [
                'pipe',
                'w',
            ],
            2 => [
                'pipe',
                'w',
            ],
        ];

        $process = proc_open(
            $command,
            $descriptors,
            $pipes
        );

        if (!is_resource($process)) {
            return [
                'ok' => false,
                'timeout' => false,
                'exitCode' => -1,
                'output' => 'Unable to start Python process.',
            ];
        }

        fclose($pipes[0]);

        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $stdout = '';
        $stderr = '';

        $start = microtime(true);
        $timedOut = false;

        while (true) {
            $status = proc_get_status($process);

            $read = [];

            if (!feof($pipes[1])) {
                $read[] = $pipes[1];
            }

            if (!feof($pipes[2])) {
                $read[] = $pipes[2];
            }

            if ($read !== []) {
                $write = null;
                $except = null;

                @stream_select(
                    $read,
                    $write,
                    $except,
                    0,
                    100000
                );

                foreach ($read as $stream) {
                    $data = stream_get_contents($stream);

                    if ($data === false || $data === '') {
                        continue;
                    }

                    if ($stream === $pipes[1]) {
                        $stdout .= $data;
                    } else {
                        $stderr .= $data;
                    }
                }
            }

            if (!$status['running']) {
                break;
            }

            if (
                $timeout > 0
                && microtime(true) - $start >= $timeout
            ) {
                $timedOut = true;

                proc_terminate($process);

                $graceStart = microtime(true);

                do {
                    $status = proc_get_status($process);

                    if (!$status['running']) {
                        break;
                    }

                    usleep(10000);
                } while (
                    microtime(true) - $graceStart < 0.25
                );

                if ($status['running']) {
                    proc_terminate(
                        $process,
                        9
                    );
                }

                break;
            }
        }

        $remainingOutput = stream_get_contents($pipes[1]);
        $remainingError = stream_get_contents($pipes[2]);

        if ($remainingOutput !== false) {
            $stdout .= $remainingOutput;
        }

        if ($remainingError !== false) {
            $stderr .= $remainingError;
        }

        fclose($pipes[1]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        $output = trim(
            $stderr !== ''
                ? $stdout . PHP_EOL . $stderr
                : $stdout
        );

        if ($timedOut) {
            return [
                'ok' => false,
                'timeout' => true,
                'exitCode' => 124,
                'output' => $output,
            ];
        }

        return [
            'ok' => $exitCode === 0,
            'timeout' => false,
            'exitCode' => $exitCode,
            'output' => $output,
        ];
    }
}
