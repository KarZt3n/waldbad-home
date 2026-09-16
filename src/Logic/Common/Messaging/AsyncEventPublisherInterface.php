<?php

namespace App\Logic\Common\Messaging;

interface AsyncEventPublisherInterface
{
    public function publish(object $event): void;
}
