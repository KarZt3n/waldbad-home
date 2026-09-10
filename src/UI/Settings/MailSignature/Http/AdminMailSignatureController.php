<?php

namespace App\UI\Settings\MailSignature\Http;

use App\Logic\Settings\MailSignature\Dto\MailSignatureResponse;
use App\Logic\Settings\MailSignature\Query\ListMailSignaturesQuery;
use App\Logic\Settings\MailSignature\UseCase\CreateMailSignatureUseCase;
use App\Logic\Settings\MailSignature\UseCase\DeleteMailSignatureUseCase;
use App\Logic\Settings\MailSignature\UseCase\UpdateMailSignatureUseCase;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Verwaltung wiederverwendbarer Mail-Signaturen (siehe `MailSignature`), die Admins im Editor einer
 * Mailvorlage (`AdminMailTemplateController`) an gewünschter Stelle einfügen können. Bewusst
 * ausschließlich für Admin/Super-Admin freigegeben (`ROLE_ADMIN`), dieselbe Begründung wie bei den
 * Mailvorlagen selbst: nicht über das reguläre, delegierbare Modul-Rechtesystem freizugeben.
 */
#[Route('/api/admin/v1/mail-signatures')]
class AdminMailSignatureController extends AbstractController
{
    #[Route('', name: 'api_admin_mail_signatures_list', methods: ['GET'])]
    public function list(ListMailSignaturesQuery $query): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        return new JsonResponse(['items' => array_map($this->toArray(...), $query->execute())]);
    }

    #[Route('', name: 'api_admin_mail_signatures_create', methods: ['POST'])]
    public function create(Request $request, CreateMailSignatureUseCase $useCase): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        $data = $request->getPayload();

        return new JsonResponse(
            $this->toArray($useCase->execute($data->getString('name'), $data->getString('body'))),
            JsonResponse::HTTP_CREATED,
        );
    }

    #[Route('/{id}', name: 'api_admin_mail_signatures_update', methods: ['PUT'])]
    public function update(string $id, Request $request, UpdateMailSignatureUseCase $useCase): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        $data = $request->getPayload();

        return new JsonResponse($this->toArray($useCase->execute($id, $data->getString('name'), $data->getString('body'))));
    }

    #[Route('/{id}', name: 'api_admin_mail_signatures_delete', methods: ['DELETE'])]
    public function delete(string $id, DeleteMailSignatureUseCase $useCase): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        $useCase->execute($id);

        return new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT);
    }

    /**
     * @return array<string, string>
     */
    private function toArray(MailSignatureResponse $signature): array
    {
        return [
            'id' => $signature->id,
            'name' => $signature->name,
            'body' => $signature->body,
        ];
    }
}
