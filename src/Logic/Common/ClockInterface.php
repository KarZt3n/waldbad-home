<?php

namespace App\Logic\Common;

interface ClockInterface
{
    public function now(): \DateTimeImmutable;
}
