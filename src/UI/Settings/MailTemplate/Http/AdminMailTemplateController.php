<?php

namespace App\UI\Settings\MailTemplate\Http;

use App\Logic\Settings\MailTemplate\Dto\MailTemplatePreview;
use App\Logic\Settings\MailTemplate\Dto\MailTemplateResponse;
use App\Logic\Settings\MailTemplate\Model\MailTemplateKey;
use App\Logic\Settings\MailTemplate\Query\GetMailTemplatesQuery;
use App\Logic\Settings\MailTemplate\UseCase\PreviewMailTemplateUseCase;
use App\Logic\Settings\MailTemplate\UseCase\ResetMailTemplateUseCase;
use App\Logic\Settings\MailTemplate\UseCase\UpdateMailTemplateUseCase;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\InputBag;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Verwaltung der Mailvorlagen (siehe `MailTemplateKey`) — die redaktionell pflegbaren Texte hinter
 * jeder automatisch verschickten E-Mail, statt fest im Code zu stehen. Bewusst ausschließlich für
 * Admin/Super-Admin freigegeben (`ROLE_ADMIN`), dieselbe Begründung wie bei PIN-Schutz und
 * Mailserver-Zugangsdaten: nicht über das reguläre, delegierbare Modul-Rechtesystem freizugeben.
 */
#[Route('/api/admin/v1/mail-templates')]
class AdminMailTemplateController extends AbstractController
{
    #[Route('', name: 'api_admin_mail_templates_list', methods: ['GET'])]
    public function list(GetMailTemplatesQuery $query): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        return new JsonResponse(['items' => array_map($this->toArray(...), $query->execute())]);
    }

    #[Route('/{key}', name: 'api_admin_mail_templates_update', methods: ['PUT'])]
    public function update(string $key, Request $request, UpdateMailTemplateUseCase $useCase, GetMailTemplatesQuery $query): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        $data = $request->getPayload();
        $useCase->execute($this->resolveKey($key), $data->getString('subject'), $data->getString('body'), $this->signatureId($data));

        return new JsonResponse(['items' => array_map($this->toArray(...), $query->execute())]);
    }

    #[Route('/{key}/reset', name: 'api_admin_mail_templates_reset', methods: ['POST'])]
    public function reset(string $key, ResetMailTemplateUseCase $useCase, GetMailTemplatesQuery $query): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        $useCase->execute($this->resolveKey($key));

        return new JsonResponse(['items' => array_map($this->toArray(...), $query->execute())]);
    }

    /**
     * Rendert den (ggf. noch ungespeicherten) Betreff/Text aus dem Editor mit Beispieldaten — für
     * den „Vorschau“-Button, siehe `PreviewMailTemplateUseCase`.
     */
    #[Route('/{key}/preview', name: 'api_admin_mail_templates_preview', methods: ['POST'])]
    public function preview(string $key, Request $request, PreviewMailTemplateUseCase $useCase): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        $data = $request->getPayload();
        $preview = $useCase->execute($this->resolveKey($key), $data->getString('subject'), $data->getString('body'), $this->signatureId($data));

        return new JsonResponse($this->previewToArray($preview));
    }

    /**
     * @return array<string, string>
     */
    private function previewToArray(MailTemplatePreview $preview): array
    {
        return [
            'subject' => $preview->subject,
            'text' => $preview->text,
            'html' => $preview->html,
        ];
    }

    private function resolveKey(string $value): MailTemplateKey
    {
        return MailTemplateKey::tryFrom($value) ?? throw new BadRequestHttpException('Unbekannte Mailvorlage.');
    }

    /**
     * @param InputBag<string|int|float|bool|null> $data
     */
    private function signatureId(InputBag $data): ?string
    {
        $value = $data->getString('signatureId', '');

        return $value === '' ? null : $value;
    }

    /**
     * @return array<string, mixed>
     */
    private function toArray(MailTemplateResponse $response): array
    {
        return [
            'key' => $response->key,
            'label' => $response->label,
            'description' => $response->description,
            'placeholders' => $response->placeholders,
            'subject' => $response->subject,
            'body' => $response->body,
            'signatureId' => $response->signatureId,
            'isDefault' => $response->isDefault,
        ];
    }
}
