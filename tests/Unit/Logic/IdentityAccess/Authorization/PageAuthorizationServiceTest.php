<?php

namespace App\Tests\Unit\Logic\IdentityAccess\Authorization;

use App\Logic\Common\Exception\AccessDeniedException;
use App\Logic\IdentityAccess\Authorization\PageAuthorizationContext;
use App\Logic\IdentityAccess\Authorization\PageAuthorizationService;
use App\Logic\IdentityAccess\User\Model\CmsModule;
use App\Logic\IdentityAccess\User\Model\ModuleAccess;
use App\Logic\IdentityAccess\User\Model\ModuleRole;
use App\Logic\IdentityAccess\User\Model\PageAccess;
use App\Logic\IdentityAccess\User\Model\PageAccessRole;
use App\Logic\IdentityAccess\User\Model\Role;
use PHPUnit\Framework\TestCase;

final class PageAuthorizationServiceTest extends TestCase
{
    private PageAuthorizationService $service;

    protected function setUp(): void
    {
        $this->service = new PageAuthorizationService();
    }

    public function testRestrictedUserOnlySeesExplicitPages(): void
    {
        $context = $this->context([
            new PageAccess('page-editor', PageAccessRole::Editor),
            new PageAccess('page-publisher', PageAccessRole::Publisher),
        ]);

        self::assertSame(['page-editor', 'page-publisher'], $this->service->visiblePageIds($context));
        $this->service->assertCanEdit($context, 'page-editor');
        $this->service->assertCanPublish($context, 'page-publisher');

        $this->expectException(AccessDeniedException::class);
        $this->service->assertCanEdit($context, 'other-page');
    }

    public function testEditorCannotPublishExplicitPage(): void
    {
        $context = $this->context([new PageAccess('page-editor', PageAccessRole::Editor)]);

        $this->expectException(AccessDeniedException::class);
        $this->service->assertCanPublish($context, 'page-editor');
    }

    public function testAdministratorIgnoresExplicitPageScope(): void
    {
        $context = new PageAuthorizationContext(
            roles: [Role::Admin],
            moduleAccess: [new ModuleAccess(CmsModule::Pages, ModuleRole::Viewer)],
            pageAccess: [new PageAccess('one-page', PageAccessRole::Editor)],
        );

        self::assertNull($this->service->visiblePageIds($context));
        self::assertTrue($this->service->canManageStructure($context));
        $this->service->assertCanEdit($context, 'any-page');
        $this->service->assertCanPublish($context, 'any-page');
    }

    /**
     * @param list<PageAccess>|null $pageAccess
     */
    private function context(?array $pageAccess): PageAuthorizationContext
    {
        return new PageAuthorizationContext(
            roles: [],
            moduleAccess: [new ModuleAccess(CmsModule::Pages, ModuleRole::Viewer)],
            pageAccess: $pageAccess,
        );
    }
}
