<?php

namespace App\UI\Membership\DirectDebit\Http;

use App\Logic\Membership\DirectDebit\Dto\DirectDebitCreditorResponse;
use App\Logic\Membership\DirectDebit\Dto\DirectDebitPositionResponse;
use App\Logic\Membership\DirectDebit\Dto\ExportPayerDirectDebitRequest;
use App\Logic\Membership\DirectDebit\Dto\PayerDirectDebitPreviewResponse;
use App\Logic\Membership\DirectDebit\Dto\UpdateDirectDebitCreditorRequest;
use App\Logic\Membership\DirectDebit\Model\SequenceType;
use App\Logic\Membership\DirectDebit\Query\GetDirectDebitCreditorQuery;
use App\Logic\Membership\DirectDebit\Query\GetPayerDirectDebitPreviewQuery;
use App\Logic\Membership\DirectDebit\UseCase\ExportPayerDirectDebitUseCase;
use App\Logic\Membership\DirectDebit\UseCase\UpdateDirectDebitCreditorUseCase;
use App\Logic\Settings\Pin\Model\ProtectedAction;
use App\Logic\Settings\Pin\UseCase\VerifyPinUseCase;
use App\UI\IdentityAccess\Security\Permission;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * SEPA-Lastschrift: Gläubigerdaten des Vereins (gepflegt bei den Beitragssätzen) sowie Vorschau
 * und Export der Lastschrift je Zahler (aus der „Gesamtberechnung für den Zahler“ eines Mitglieds).
 */
final class AdminDirectDebitController extends AbstractController
{
    #[Route('/api/admin/v1/direct-debit-creditor', name: 'api_admin_direct_debit_creditor_get', methods: ['GET'])]
    public function creditor(GetDirectDebitCreditorQuery $query): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::ContributionRatesView->value);

        return new JsonResponse($this->creditorToArray($query->execute()));
    }

    #[Route('/api/admin/v1/direct-debit-creditor', name: 'api_admin_direct_debit_creditor_update', methods: ['PUT'])]
    public function updateCreditor(Request $request, UpdateDirectDebitCreditorUseCase $useCase): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::ContributionRatesEdit->value);
        $payload = $request->getPayload();

        return new JsonResponse($this->creditorToArray($useCase->execute(new UpdateDirectDebitCreditorRequest(
            name: $this->optionalString($payload->getString('name')),
            creditorId: $this->optionalString($payload->getString('creditorId')),
            iban: $this->optionalString($payload->getString('iban')),
            bic: $this->optionalString($payload->getString('bic')),
        ))));
    }

    #[Route('/api/admin/v1/members/{id}/direct-debit-preview', name: 'api_admin_member_direct_debit_preview', methods: ['GET'])]
    public function preview(string $id, GetPayerDirectDebitPreviewQuery $query): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::MembersEdit->value);

        return new JsonResponse($this->previewToArray($query->execute($id)));
    }

    // POST statt GET: der optionale PIN landet im JSON-Body statt in der Query-String.
    #[Route('/api/admin/v1/members/{id}/direct-debit-export', name: 'api_admin_member_direct_debit_export', methods: ['POST'])]
    public function export(string $id, Request $request, ExportPayerDirectDebitUseCase $useCase, VerifyPinUseCase $verifyPin): Response
    {
        $this->denyAccessUnlessGranted(Permission::MembersEdit->value);
        $payload = $request->getPayload();
        $pin = $payload->getString('pin');
        $verifyPin->execute(ProtectedAction::MembersDirectDebitExport, $pin === '' ? null : $pin);

        $collectionDate = \DateTimeImmutable::createFromFormat('!Y-m-d', $payload->getString('collectionDate'));
        if ($collectionDate === false) {
            throw new BadRequestHttpException('Das Fälligkeitsdatum ist ungültig.');
        }
        $sequenceType = SequenceType::tryFrom($payload->getString('sequenceType'))
            ?? throw new BadRequestHttpException('Der Lastschrifttyp ist ungültig.');
        $positionIds = [];
        foreach ($payload->all('positionIds') as $positionId) {
            if (!is_string($positionId)) {
                throw new BadRequestHttpException('Die Positionsauswahl ist ungültig.');
            }
            $positionIds[] = $positionId;
        }

        $file = $useCase->execute(new ExportPayerDirectDebitRequest(
            memberId: $id,
            collectionDate: $collectionDate,
            sequenceType: $sequenceType,
            remittanceInformation: $payload->getString('remittanceInformation'),
            positionIds: $positionIds,
        ));

        $response = new Response($file->content);
        $response->headers->set('Content-Type', 'application/xml; charset=UTF-8');
        $response->headers->set('Content-Disposition', sprintf('attachment; filename="%s"', $file->fileName));

        return $response;
    }

    private function optionalString(string $value): ?string
    {
        return trim($value) === '' ? null : trim($value);
    }

    /**
     * @return array<string, string|bool|null>
     */
    private function creditorToArray(DirectDebitCreditorResponse $creditor): array
    {
        return [
            'name' => $creditor->name,
            'creditorId' => $creditor->creditorId,
            'iban' => $creditor->iban,
            'bic' => $creditor->bic,
            'complete' => $creditor->complete,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function previewToArray(PayerDirectDebitPreviewResponse $preview): array
    {
        return [
            'payerId' => $preview->payerId,
            'payerMemberNumber' => $preview->payerMemberNumber,
            'payerName' => $preview->payerName,
            'debtorName' => $preview->debtorName,
            'iban' => $preview->iban,
            'bankName' => $preview->bankName,
            'mandateReference' => $preview->mandateReference,
            'mandateSignedOn' => $preview->mandateSignedOn,
            'paymentInterval' => $preview->paymentInterval,
            'creditor' => $this->creditorToArray($preview->creditor),
            'positions' => array_map(static fn (DirectDebitPositionResponse $position): array => [
                'id' => $position->id,
                'memberId' => $position->memberId,
                'memberNumber' => $position->memberNumber,
                'memberName' => $position->memberName,
                'kind' => $position->kind,
                'label' => $position->label,
                'amountCents' => $position->amountCents,
                'annualAmountCents' => $position->annualAmountCents,
                'selectedByDefault' => $position->selectedByDefault,
            ], $preview->positions),
            'blockers' => $preview->blockers,
            'warnings' => $preview->warnings,
            'defaultCollectionDate' => $preview->defaultCollectionDate,
            'defaultSequenceType' => $preview->defaultSequenceType,
            'defaultRemittanceInformation' => $preview->defaultRemittanceInformation,
            'sequenceTypes' => $preview->sequenceTypes,
            'contributionYear' => $preview->contributionYear,
            'joiningYearDebit' => $preview->joiningYearDebit,
            'lastDebit' => $preview->lastDebit === null ? null : [
                'contributionYear' => $preview->lastDebit->contributionYear,
                'sequenceType' => $preview->lastDebit->sequenceType,
                'collectionDate' => $preview->lastDebit->collectionDate,
                'amountCents' => $preview->lastDebit->amountCents,
                'exportedAt' => $preview->lastDebit->exportedAt,
            ],
        ];
    }
}
