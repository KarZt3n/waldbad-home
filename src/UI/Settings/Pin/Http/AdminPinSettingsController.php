<?php

namespace App\UI\Settings\Pin\Http;

use App\Logic\Settings\Pin\Dto\PinSettingsResponse;
use App\Logic\Settings\Pin\Model\ProtectedAction;
use App\Logic\Settings\Pin\Query\GetPinSettingsQuery;
use App\Logic\Settings\Pin\UseCase\ClearActionPinUseCase;
use App\Logic\Settings\Pin\UseCase\SetActionPinUseCase;
use App\Logic\Settings\Pin\UseCase\SetGlobalPinUseCase;
use App\Logic\Settings\Pin\UseCase\UpdateProtectedActionsUseCase;
use App\Logic\Settings\Pin\UseCase\VerifyPinUseCase;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Verwaltung des PIN-Schutzes: ein globaler PIN als Rückfall sowie optional je Modul/Funktionalität
 * (`ProtectedAction`) ein eigener, abweichender PIN. Bewusst ausschließlich für Admin/Super-Admin
 * freigegeben (`ROLE_ADMIN`, per Rollenhierarchie schließt das Super-Admin ein) — nicht über das
 * reguläre Modul-Rechtesystem delegierbar, da genau das die Absicht dieses Schutzes wäre.
 */
#[Route('/api/admin/v1/pin-settings')]
class AdminPinSettingsController extends AbstractController
{
    public function __construct(private readonly RateLimiterFactory $pinVerificationLimiter)
    {
    }

    #[Route('', name: 'api_admin_pin_settings_get', methods: ['GET'])]
    public function get(GetPinSettingsQuery $query): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        return new JsonResponse($this->toArray($query->execute()));
    }

    #[Route('/pin', name: 'api_admin_pin_settings_set_global_pin', methods: ['PUT'])]
    public function setGlobalPin(Request $request, SetGlobalPinUseCase $useCase, GetPinSettingsQuery $query): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        $useCase->execute($request->getPayload()->getString('pin'));

        return new JsonResponse($this->toArray($query->execute()));
    }

    #[Route('/actions/{action}/pin', name: 'api_admin_pin_settings_set_action_pin', methods: ['PUT'])]
    public function setActionPin(string $action, Request $request, SetActionPinUseCase $useCase, GetPinSettingsQuery $query): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        $useCase->execute($this->resolveAction($action), $request->getPayload()->getString('pin'));

        return new JsonResponse($this->toArray($query->execute()));
    }

    #[Route('/actions/{action}/pin', name: 'api_admin_pin_settings_clear_action_pin', methods: ['DELETE'])]
    public function clearActionPin(string $action, ClearActionPinUseCase $useCase, GetPinSettingsQuery $query): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        $useCase->execute($this->resolveAction($action));

        return new JsonResponse($this->toArray($query->execute()));
    }

    #[Route('/protected-actions', name: 'api_admin_pin_settings_update_protected_actions', methods: ['PUT'])]
    public function updateProtectedActions(Request $request, UpdateProtectedActionsUseCase $useCase, GetPinSettingsQuery $query): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        /** @var list<string> $actionKeys */
        $actionKeys = $request->getPayload()->all('protectedActions');
        $useCase->execute($actionKeys);

        return new JsonResponse($this->toArray($query->execute()));
    }

    #[Route('/verify', name: 'api_admin_pin_settings_verify', methods: ['POST'])]
    public function verify(Request $request, VerifyPinUseCase $useCase): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        $limit = $this->pinVerificationLimiter->create($this->getUser()?->getUserIdentifier() ?? $request->getClientIp() ?? 'unknown')->consume();
        if (!$limit->isAccepted()) {
            throw new TooManyRequestsHttpException($limit->getRetryAfter()->getTimestamp() - time(), 'Zu viele Versuche. Bitte kurz warten.');
        }

        $payload = $request->getPayload();
        $useCase->execute($this->resolveAction($payload->getString('action')), $payload->getString('pin', ''));

        return new JsonResponse(['valid' => true]);
    }

    private function resolveAction(string $value): ProtectedAction
    {
        return ProtectedAction::tryFrom($value) ?? throw new BadRequestHttpException('Unbekannte geschützte Aktion.');
    }

    /**
     * @return array<string, mixed>
     */
    private function toArray(PinSettingsResponse $response): array
    {
        return [
            'globalPinIsSet' => $response->globalPinIsSet,
            'protectedActions' => $response->protectedActions,
            'availableActions' => array_map(
                static fn (ProtectedAction $action): array => [
                    'key' => $action->value,
                    'label' => $action->label(),
                    'category' => $action->category(),
                    'hasOwnPin' => in_array($action->value, $response->actionsWithOwnPin, true),
                ],
                ProtectedAction::cases(),
            ),
        ];
    }
}
