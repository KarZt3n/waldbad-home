<?php

namespace App\UI\IdentityAccess\User\Http;

use App\Logic\IdentityAccess\User\Dto\CreateUserRequest;
use App\Logic\IdentityAccess\User\Model\Role;
use App\Logic\IdentityAccess\User\Query\ListUsersQuery;
use App\Logic\IdentityAccess\User\UseCase\ChangeUserRolesUseCase;
use App\Logic\IdentityAccess\User\UseCase\CreateUserUseCase;
use App\Logic\IdentityAccess\User\UseCase\SuspendUserUseCase;
use App\UI\Common\Http\ApiResponseFactory;
use App\UI\IdentityAccess\Security\Permission;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\InputBag;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/admin/v1/users')]
class AdminUserController extends AbstractController
{
    public function __construct(private readonly ApiResponseFactory $responseFactory)
    {
    }

    #[Route('', name: 'api_admin_users_list', methods: ['GET'])]
    public function list(ListUsersQuery $query): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::UserManage->value);

        return $this->responseFactory->users($query->execute());
    }

    #[Route('', name: 'api_admin_users_create', methods: ['POST'])]
    public function create(Request $request, CreateUserUseCase $useCase): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::UserManage->value);
        $data = $request->getPayload();

        $user = $useCase->execute(new CreateUserRequest(
            email: $this->requiredString($data, 'email'),
            displayName: $this->requiredString($data, 'displayName'),
            plainPassword: $this->requiredString($data, 'password'),
            roles: $this->parseRoles($data),
        ));

        return $this->responseFactory->user($user, JsonResponse::HTTP_CREATED);
    }

    #[Route('/{id}/roles', name: 'api_admin_users_roles', methods: ['PUT'])]
    public function roles(string $id, Request $request, ChangeUserRolesUseCase $useCase): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::RoleManage->value);

        return $this->responseFactory->user($useCase->execute($id, $this->parseRoles($request->getPayload())));
    }

    #[Route('/{id}/suspend', name: 'api_admin_users_suspend', methods: ['POST'])]
    public function suspend(string $id, SuspendUserUseCase $useCase): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::UserManage->value);

        return $this->responseFactory->user($useCase->execute($id));
    }

    /**
     * @param InputBag<string|int|float|bool|null> $data
     * @return list<Role>
     */
    private function parseRoles(InputBag $data): array
    {
        $values = $data->all('roles');
        if ($values === [] || !array_is_list($values)) {
            throw new BadRequestHttpException('Das Feld "roles" muss mindestens eine Rolle enthalten.');
        }

        $roles = [];
        foreach ($values as $value) {
            if (!is_string($value)) {
                throw new BadRequestHttpException('Jede Rolle muss eine Zeichenkette sein.');
            }
            try {
                $roles[] = Role::from($value);
            } catch (\ValueError) {
                throw new BadRequestHttpException(sprintf('Die Rolle "%s" ist ungültig.', $value));
            }
        }

        return array_values(array_unique($roles, SORT_REGULAR));
    }

    /**
     * @param InputBag<string|int|float|bool|null> $data
     */
    private function requiredString(InputBag $data, string $key): string
    {
        $value = trim($data->getString($key));
        if ($value === '') {
            throw new BadRequestHttpException(sprintf('Das Feld "%s" ist erforderlich.', $key));
        }

        return $value;
    }
}
