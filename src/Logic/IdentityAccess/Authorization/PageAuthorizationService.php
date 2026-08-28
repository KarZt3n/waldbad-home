<?php

namespace App\Logic\IdentityAccess\Authorization;

use App\Logic\Common\Exception\AccessDeniedException;
use App\Logic\IdentityAccess\User\Model\CmsModule;
use App\Logic\IdentityAccess\User\Model\ModuleRole;
use App\Logic\IdentityAccess\User\Model\PageAccess;
use App\Logic\IdentityAccess\User\Model\PageAccessRole;
use App\Logic\IdentityAccess\User\Model\Role;

readonly class PageAuthorizationService
{
    /**
     * @return list<string>|null Null bedeutet, dass alle Seiten sichtbar sind.
     */
    public function visiblePageIds(PageAuthorizationContext $context): ?array
    {
        if ($this->isAdministrator($context) || $context->pageAccess === null) {
            return null;
        }

        return array_map(static fn (PageAccess $access): string => $access->pageId, $context->pageAccess);
    }

    public function assertCanEdit(PageAuthorizationContext $context, string $pageId): void
    {
        if (!$this->canEdit($context, $pageId)) {
            throw new AccessDeniedException('Für diese Seite fehlt die Bearbeitungsberechtigung.');
        }
    }

    public function assertCanPublish(PageAuthorizationContext $context, string $pageId): void
    {
        if (!$this->canPublish($context, $pageId)) {
            throw new AccessDeniedException('Für diese Seite fehlt die Veröffentlichungsberechtigung.');
        }
    }

    public function assertCanManageStructure(PageAuthorizationContext $context): void
    {
        if (!$this->canManageStructure($context)) {
            throw new AccessDeniedException('Eingeschränkte Seitenzugänge dürfen die Seitenstruktur nicht verändern.');
        }
    }

    private function canEdit(PageAuthorizationContext $context, string $pageId): bool
    {
        if ($this->isAdministrator($context)) {
            return true;
        }
        if ($context->pageAccess !== null) {
            return $this->pageRole($context, $pageId) !== null;
        }

        return in_array($this->moduleRole($context), [ModuleRole::Editor, ModuleRole::Publisher, ModuleRole::Moderator], true);
    }

    private function canPublish(PageAuthorizationContext $context, string $pageId): bool
    {
        if ($this->isAdministrator($context)) {
            return true;
        }
        if ($context->pageAccess !== null) {
            return $this->pageRole($context, $pageId) === PageAccessRole::Publisher;
        }

        return $this->moduleRole($context) === ModuleRole::Publisher;
    }

    public function canManageStructure(PageAuthorizationContext $context): bool
    {
        if ($this->isAdministrator($context)) {
            return true;
        }

        return $context->pageAccess === null
            && in_array($this->moduleRole($context), [ModuleRole::Editor, ModuleRole::Publisher, ModuleRole::Moderator], true);
    }

    private function pageRole(PageAuthorizationContext $context, string $pageId): ?PageAccessRole
    {
        foreach ($context->pageAccess ?? [] as $access) {
            if ($access->pageId === $pageId) {
                return $access->role;
            }
        }

        return null;
    }

    private function moduleRole(PageAuthorizationContext $context): ?ModuleRole
    {
        foreach ($context->moduleAccess as $access) {
            if ($access->module === CmsModule::Pages) {
                return $access->role;
            }
        }

        return null;
    }

    private function isAdministrator(PageAuthorizationContext $context): bool
    {
        return in_array(Role::Admin, $context->roles, true)
            || in_array(Role::SuperAdmin, $context->roles, true);
    }
}
