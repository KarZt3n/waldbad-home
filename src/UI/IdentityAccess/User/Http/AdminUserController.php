<?php

namespace App\UI\IdentityAccess\User\Http;

use App\Logic\IdentityAccess\User\Dto\CreateUserRequest;
use App\Logic\IdentityAccess\User\Model\CmsModule;
use App\Logic\IdentityAccess\User\Model\ModuleAccess;
use App\Logic\IdentityAccess\User\Model\ModuleRole;
use App\Logic\IdentityAccess\User\Model\Role;
use App\Logic\IdentityAccess\User\Query\ListUsersQuery;
use App\Logic\IdentityAccess\User\UseCase\ChangeUserAccessUseCase;
use App\Logic\IdentityAccess\User\UseCase\CreateUserUseCase;
use App\Logic\IdentityAccess\User\UseCase\SuspendUserUseCase;
use App\UI\Common\Http\ApiResponseFactory;
use App\UI\IdentityAccess\Security\AuthenticatedUser;
use App\UI\IdentityAccess\Security\Permission;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\InputBag;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api/admin/v1/users')]
class AdminUserController extends AbstractController
{
    public function __construct(private readonly ApiResponseFactory $responseFactory)
    {
    }

    #[Route('', name: 'api_admin_users_list', methods: ['GET'])]
    public function list(ListUsersQuery $query): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::UserManagementView->value);

        return $this->responseFactory->users($query->execute());
    }

    #[Route('', name: 'api_admin_users_create', methods: ['POST'])]
    public function create(Request $request, CreateUserUseCase $useCase, #[CurrentUser] AuthenticatedUser $actor): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::UserManagementEdit->value);
        $data = $request->getPayload();
        $roles = $this->parseRoles($data);
        if (in_array(Role::SuperAdmin, $roles, true)) {
            $this->denyAccessUnlessGranted(Permission::RoleManage->value);
        } elseif (in_array(Role::Admin, $roles, true) && !$this->isAdministrator($actor)) {
            $this->denyAccessUnlessGranted('ROLE_ADMIN');
        }

        $user = $useCase->execute(new CreateUserRequest(
            email: $this->requiredString($data, 'email'),
            displayName: $this->requiredString($data, 'displayName'),
            plainPassword: $this->requiredString($data, 'password'),
            roles: $roles,
            moduleAccess: $this->parseModuleAccess($data),
        ));

        return $this->responseFactory->user($user, JsonResponse::HTTP_CREATED);
    }

    #[Route('/{id}/access', name: 'api_admin_users_access', methods: ['PUT'])]
    public function access(
        string $id,
        Request $request,
        ChangeUserAccessUseCase $useCase,
        #[CurrentUser] AuthenticatedUser $actor,
    ): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::UserManagementEdit->value);
        $data = $request->getPayload();

        return $this->responseFactory->user($useCase->execute(
            $id,
            $this->parseRoles($data),
            $this->parseModuleAccess($data),
            $actor->getDomainRoles(),
        ));
    }

    #[Route('/{id}/suspend', name: 'api_admin_users_suspend', methods: ['POST'])]
    public function suspend(string $id, SuspendUserUseCase $useCase, #[CurrentUser] AuthenticatedUser $actor): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::UserManagementEdit->value);

        return $this->responseFactory->user($useCase->execute($id, $actor->getDomainRoles()));
    }

    /**
     * @param InputBag<string|int|float|bool|null> $data
     * @return list<Role>
     */
    private function parseRoles(InputBag $data): array
    {
        $values = $data->all('roles');
        if (!array_is_list($values)) {
            throw new BadRequestHttpException('Das Feld "roles" muss eine Liste sein.');
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

        $roles = array_values(array_unique($roles, SORT_REGULAR));
        if (count($roles) > 1) {
            throw new BadRequestHttpException('Es darf höchstens eine globale Administratorrolle ausgewählt werden.');
        }

        return $roles;
    }

    /**
     * @param InputBag<string|int|float|bool|null> $data
     * @return list<ModuleAccess>
     */
    private function parseModuleAccess(InputBag $data): array
    {
        $values = $data->all('moduleAccess');
        if ($values === [] || array_is_list($values)) {
            throw new BadRequestHttpException('Das Feld "moduleAccess" muss mindestens eine Modulrolle enthalten.');
        }

        $moduleAccess = [];
        foreach ($values as $moduleValue => $roleValue) {
            if (!is_string($moduleValue) || !is_string($roleValue)) {
                throw new BadRequestHttpException('Modul und Modulrolle müssen Zeichenketten sein.');
            }
            try {
                $moduleAccess[] = new ModuleAccess(CmsModule::from($moduleValue), ModuleRole::from($roleValue));
            } catch (\ValueError) {
                throw new BadRequestHttpException(sprintf('Die Modulzuordnung "%s: %s" ist ungültig.', $moduleValue, $roleValue));
            }
        }

        return $moduleAccess;
    }

    private function isAdministrator(AuthenticatedUser $user): bool
    {
        return in_array(Role::Admin, $user->getDomainRoles(), true)
            || in_array(Role::SuperAdmin, $user->getDomainRoles(), true);
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
