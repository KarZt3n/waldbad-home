<?php

namespace App\UI\Overview\Http;

use App\Logic\IdentityAccess\User\Model\CmsModule;
use App\Logic\IdentityAccess\User\Model\ModuleAccess;
use App\Logic\Overview\Dto\GetOverviewRequest;
use App\Logic\Overview\Dto\OverviewTaskResponse;
use App\Logic\Overview\Query\GetOverviewQuery;
use App\UI\IdentityAccess\Security\AuthenticatedUser;
use App\UI\IdentityAccess\Security\Permission;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api/admin/v1/overview')]
class AdminOverviewController extends AbstractController
{
    #[Route('', name: 'api_admin_overview', methods: ['GET'])]
    public function overview(#[CurrentUser] AuthenticatedUser $user, GetOverviewQuery $query): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::CmsRead->value);
        $tasks = $query->execute(new GetOverviewRequest(array_map(
            static fn (ModuleAccess $access): CmsModule => $access->module,
            $user->getModuleAccess(),
        )));

        return new JsonResponse(['items' => array_map(static fn (OverviewTaskResponse $task): array => [
            'type' => $task->type->value,
            'module' => $task->type->module()->value,
            'count' => $task->count,
            'earliestAt' => $task->earliestAt?->format(\DateTimeInterface::ATOM),
        ], $tasks)]);
    }
}
