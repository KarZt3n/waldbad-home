<?php

namespace App\Logic\IdentityAccess\User\Model;

use App\Logic\Common\Exception\BusinessRuleViolationException;

readonly class PageAccess
{
    public function __construct(
        public string $pageId,
        public PageAccessRole $role,
    ) {
        if (trim($this->pageId) === '') {
            throw new BusinessRuleViolationException('Eine Seitenberechtigung benötigt eine Seiten-ID.');
        }
    }
}
