<?php

declare(strict_types=1);

namespace Tools\Doctor\Contracts;

interface CheckIdentityInterface
{
    public function id(): string;
}
