<?php

declare(strict_types=1);

namespace Tools\Doctor\Snapshot;

final class ProjectSnapshotBuilder
{
    public function build(
        ?string $domainRoot = null
    ): ProjectSnapshot {
        return (new SourceSnapshotBuilder())
            ->build(
                static function (
                    string $path
                ): bool {
                    return !str_contains(
                        str_replace(
                            "\\",
                            "/",
                            $path
                        ),
                        "/tools/Doctor/"
                    );
                },
                $domainRoot
            );
    }
}
