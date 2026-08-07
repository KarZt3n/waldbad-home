<?php

namespace App\Data\Common;

use App\Logic\Common\ClockInterface;

readonly class SystemClock implements ClockInterface
{
    public function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable();
    }
}
