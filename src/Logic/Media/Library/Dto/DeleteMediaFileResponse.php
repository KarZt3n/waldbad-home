<?php

namespace App\Logic\Media\Library\Dto;

readonly class DeleteMediaFileResponse
{
    public function __construct(public int $removedReferences)
    {
    }
}
