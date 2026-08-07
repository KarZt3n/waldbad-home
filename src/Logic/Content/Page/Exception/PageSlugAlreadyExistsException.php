<?php

namespace App\Logic\Content\Page\Exception;

use App\Logic\Common\Exception\BusinessRuleViolationException;

class PageSlugAlreadyExistsException extends BusinessRuleViolationException
{
    public function __construct(string $slug)
    {
        parent::__construct(sprintf('Der Slug "%s" wird bereits verwendet.', $slug));
    }
}
