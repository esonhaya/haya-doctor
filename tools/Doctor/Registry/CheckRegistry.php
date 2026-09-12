<?php

declare(strict_types=1);

namespace Tools\Doctor\Registry;

use ReflectionClass;
use RuntimeException;
use Tools\Doctor\Contracts\CheckIdentityInterface;
use Tools\Doctor\Contracts\CheckInterface;

final class CheckRegistry
{
    /** @var array<string,CheckInterface> */
    private array $registered = [];

    /**
     * Register an already constructed host check.
     */
    public function register(CheckInterface $check, ?string $id = null): void
    {
        $id ??= self::idFor($check);
        $id = trim($id);

        if ($id === '') {
            throw new RuntimeException('Doctor check IDs cannot be empty.');
        }

        if (isset($this->registered[$id])) {
            throw new RuntimeException(sprintf('Duplicate Doctor check ID "%s".', $id));
        }

        $this->registered[$id] = $check;
    }

    /**
     * Register check directories supplied by the host.
     *
     * @param array<int,string> $directories
     * @return CheckInterface[]
     */
    public function fromDirectories(
        array $directories
    ): array {
        $checks = [];

        foreach ($directories as $directory) {
            if (!is_dir($directory)) {
                throw new RuntimeException(
                    "Doctor check directory not found: {$directory}"
                );
            }

            $files = glob(
                rtrim($directory, DIRECTORY_SEPARATOR)
                . DIRECTORY_SEPARATOR
                . '*.php'
            );

            if ($files === false) {
                throw new RuntimeException(
                    "Unable to scan Doctor check directory: {$directory}"
                );
            }

            sort($files, SORT_STRING);

            foreach ($files as $file) {
                $class = $this->classFromFile($file);

                if ($class === null) {
                    throw new RuntimeException(
                        "Unable to resolve Doctor check class: {$file}"
                    );
                }

                if (!class_exists($class)) {
                    require_once $file;
                }

                if (!class_exists($class)) {
                    throw new RuntimeException(
                        "Doctor check class not found after loading: {$class}"
                    );
                }

                $reflection =
                    new ReflectionClass($class);

                if (
                    $reflection->isAbstract()
                    || !$reflection->implementsInterface(
                        CheckInterface::class
                    )
                ) {
                    continue;
                }

                $check = new $class();
                $this->register($check);
            }
        }

        return $this->all();
    }

    /**
     * @param array<int,string> $directories
     * @return CheckInterface[]
     */
    public function all(
        array $directories = []
    ): array {
        if ($directories !== []) {
            return $this->fromDirectories($directories);
        }

        $checks = $this->registered;
        uasort(
            $checks,
            static fn(CheckInterface $a, CheckInterface $b): int =>
                ($a->priority() <=> $b->priority())
                ?: (self::idFor($a) <=> self::idFor($b))
        );

        return array_values($checks);
    }

    /**
     * Register project-supplied check instances without coupling the core to
     * a project's constructors or configuration.
     *
     * @param CheckInterface[] $checks
     * @return CheckInterface[]
     */
    public function fromChecks(array $checks): array
    {
        foreach ($checks as $check) {
            if (!$check instanceof CheckInterface) {
                throw new RuntimeException('All supplied Doctor checks must implement CheckInterface.');
            }
            $this->register($check);
        }

        return $this->all();
    }

    public static function idFor(CheckInterface $check): string
    {
        if ($check instanceof CheckIdentityInterface) {
            $id = trim($check->id());
            if ($id !== '') {
                return $id;
            }
        }

        $shortName = (new ReflectionClass($check))->getShortName();
        $id = preg_replace('/Check$/', '', $shortName) ?: $shortName;
        $id = preg_replace('/(?<!^)[A-Z]/', '.$0', $id) ?: $id;

        return strtolower($id);
    }

    private function classFromFile(
        string $file
    ): ?string {
        $source = file_get_contents($file);

        if ($source === false) {
            return null;
        }

        if (
            preg_match(
                '/namespace\s+([^;]+);/s',
                $source,
                $namespace
            ) !== 1
        ) {
            return null;
        }

        if (
            preg_match(
                '/\bclass\s+([A-Za-z_][A-Za-z0-9_]*)\b/s',
                $source,
                $class
            ) !== 1
        ) {
            return null;
        }

        return trim($namespace[1])
            . '\\'
            . $class[1];
    }
}
