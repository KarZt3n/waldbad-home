<?php

namespace App\UI\Settings\Email\Http;

use App\Logic\Settings\Email\Dto\EmailSettingsResponse;
use App\Logic\Settings\Email\Dto\UpdateEmailSettingsRequest;
use App\Logic\Settings\Email\Model\EmailProviderPreset;
use App\Logic\Settings\Email\Model\NotificationEvent;
use App\Logic\Settings\Email\Query\GetEmailSettingsQuery;
use App\Logic\Settings\Email\UseCase\SendTestEmailUseCase;
use App\Logic\Settings\Email\UseCase\UpdateEmailSettingsUseCase;
use App\Logic\Settings\Email\UseCase\UpdateNotificationRecipientsUseCase;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Verwaltung der SMTP-Zugangsdaten für den Mailversand sowie der Empfänger je Benachrichtigungs-
 * Ereignis (`NotificationEvent`). Bewusst ausschließlich für Admin/Super-Admin freigegeben
 * (`ROLE_ADMIN`, per Rollenhierarchie schließt das Super-Admin ein) — dieselbe Begründung wie beim
 * PIN-Schutz (`AdminPinSettingsController`): Mailserver-Zugangsdaten sind ebenso sensibel und nicht
 * über das reguläre, delegierbare Modul-Rechtesystem freizugeben.
 */
#[Route('/api/admin/v1/email-settings')]
class AdminEmailSettingsController extends AbstractController
{
    #[Route('', name: 'api_admin_email_settings_get', methods: ['GET'])]
    public function get(GetEmailSettingsQuery $query): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        return new JsonResponse($this->toArray($query->execute()));
    }

    #[Route('', name: 'api_admin_email_settings_update', methods: ['PUT'])]
    public function update(Request $request, UpdateEmailSettingsUseCase $useCase, GetEmailSettingsQuery $query): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        $data = $request->getPayload();
        $providerValue = $data->getString('provider', '');
        $portValue = $data->getString('port', '');
        $useCase->execute(new UpdateEmailSettingsRequest(
            provider: $providerValue === '' ? null : (EmailProviderPreset::tryFrom($providerValue)
                ?? throw new BadRequestHttpException('Unbekannter Anbieter.')),
            host: $data->getString('host', '') === '' ? null : $data->getString('host'),
            port: $portValue === '' ? null : (int) $portValue,
            username: $data->getString('username', '') === '' ? null : $data->getString('username'),
            password: $data->getString('password', '') === '' ? null : $data->getString('password'),
            fromAddress: $data->getString('fromAddress', '') === '' ? null : $data->getString('fromAddress'),
            fromName: $data->getString('fromName', '') === '' ? null : $data->getString('fromName'),
        ));

        return new JsonResponse($this->toArray($query->execute()));
    }

    #[Route('/notifications/{event}', name: 'api_admin_email_settings_update_notifications', methods: ['PUT'])]
    public function updateNotificationRecipients(string $event, Request $request, UpdateNotificationRecipientsUseCase $useCase, GetEmailSettingsQuery $query): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        /** @var list<string> $recipients */
        $recipients = $request->getPayload()->all('recipients');
        $useCase->execute($this->resolveEvent($event), $recipients);

        return new JsonResponse($this->toArray($query->execute()));
    }

    #[Route('/test', name: 'api_admin_email_settings_test', methods: ['POST'])]
    public function sendTest(Request $request, SendTestEmailUseCase $useCase): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        $useCase->execute($request->getPayload()->getString('to'));

        return new JsonResponse(['sent' => true]);
    }

    private function resolveEvent(string $value): NotificationEvent
    {
        return NotificationEvent::tryFrom($value) ?? throw new BadRequestHttpException('Unbekanntes Benachrichtigungs-Ereignis.');
    }

    /**
     * @return array<string, mixed>
     */
    private function toArray(EmailSettingsResponse $response): array
    {
        return [
            'provider' => $response->provider,
            'host' => $response->host,
            'port' => $response->port,
            'username' => $response->username,
            'passwordIsSet' => $response->passwordIsSet,
            'fromAddress' => $response->fromAddress,
            'fromName' => $response->fromName,
            'configured' => $response->configured,
            'notificationRecipients' => $response->notificationRecipients,
            'providerPresets' => array_map(
                static fn (EmailProviderPreset $preset): array => [
                    'key' => $preset->value,
                    'label' => $preset->label(),
                    'defaultHost' => $preset->defaultHost(),
                    'defaultPort' => $preset->defaultPort(),
                    'hint' => $preset->hint(),
                ],
                EmailProviderPreset::cases(),
            ),
            'notificationEvents' => array_map(
                static fn (NotificationEvent $event): array => ['key' => $event->value, 'label' => $event->label()],
                NotificationEvent::cases(),
            ),
        ];
    }
}
