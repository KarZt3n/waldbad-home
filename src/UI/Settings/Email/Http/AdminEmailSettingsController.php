<?php

namespace App\UI\Settings\Email\Http;

use App\Logic\Settings\Email\Dto\EmailSettingsResponse;
use App\Logic\Settings\Email\Model\NotificationEvent;
use App\Logic\Settings\Email\Query\GetEmailSettingsQuery;
use App\Logic\Settings\Email\UseCase\UpdateNotificationRecipientsUseCase;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Verwaltung der Empfänger je Benachrichtigungs-Ereignis (`NotificationEvent`). Der Mailversand
 * selbst (SMTP-Zugangsdaten, Absenderadresse) ist nicht mehr hier admin-editierbar, sondern kommt
 * aus der Deployment-Konfiguration (`MAILER_DSN`/`MAILER_FROM_ADDRESS`/`MAILER_FROM_NAME`, siehe
 * `NotificationMailer`). Bewusst ausschließlich für Admin/Super-Admin freigegeben (`ROLE_ADMIN`, per
 * Rollenhierarchie schließt das Super-Admin ein) — dieselbe Begründung wie beim PIN-Schutz
 * (`AdminPinSettingsController`): wer benachrichtigt wird, ist nicht über das reguläre,
 * delegierbare Modul-Rechtesystem freizugeben.
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

    #[Route('/notifications/{event}', name: 'api_admin_email_settings_update_notifications', methods: ['PUT'])]
    public function updateNotificationRecipients(string $event, Request $request, UpdateNotificationRecipientsUseCase $useCase, GetEmailSettingsQuery $query): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        /** @var list<string> $recipients */
        $recipients = $request->getPayload()->all('recipients');
        $useCase->execute($this->resolveEvent($event), $recipients);

        return new JsonResponse($this->toArray($query->execute()));
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
            'notificationRecipients' => $response->notificationRecipients,
            'notificationEvents' => array_map(
                static fn (NotificationEvent $event): array => ['key' => $event->value, 'label' => $event->label()],
                NotificationEvent::cases(),
            ),
        ];
    }
}
