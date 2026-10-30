<?php

namespace App\UI\Membership\WorkAssignmentRefund\Http;

use App\Logic\Membership\WorkAssignment\Model\WorkAssignmentParticipation;
use App\Logic\Membership\WorkAssignmentRefund\Dto\ExportHouseholdWorkAssignmentRefundRequest;
use App\Logic\Membership\WorkAssignmentRefund\Dto\ExportWorkAssignmentRefundsRequest;
use App\Logic\Membership\WorkAssignmentRefund\Dto\WorkAssignmentRefundFileResponse;
use App\Logic\Membership\WorkAssignmentRefund\Dto\WorkAssignmentRefundMemberResponse;
use App\Logic\Membership\WorkAssignmentRefund\Dto\WorkAssignmentRefundOverviewEntryResponse;
use App\Logic\Membership\WorkAssignmentRefund\Model\WorkAssignmentRefundRecord;
use App\Logic\Membership\WorkAssignmentRefund\Query\GetWorkAssignmentRefundDetailQuery;
use App\Logic\Membership\WorkAssignmentRefund\Query\GetWorkAssignmentRefundOverviewQuery;
use App\Logic\Membership\WorkAssignmentRefund\UseCase\ExportHouseholdWorkAssignmentRefundUseCase;
use App\Logic\Membership\WorkAssignmentRefund\UseCase\ExportWorkAssignmentRefundsUseCase;
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
 * Gutschrift der geleisteten Arbeitsstunden: Übersicht je Beitragsjahr über alle Haushalte und
 * Sammelüberweisung als SEPA-Datei (pain.001). Geschützt wie der Lastschrift-Export, da beides
 * Geldbewegungen vom bzw. auf das Vereinskonto auslöst.
 */
final class AdminWorkAssignmentRefundController extends AbstractController
{
    #[Route('/api/admin/v1/work-assignment-refunds', name: 'api_admin_work_assignment_refund_overview', methods: ['GET'])]
    public function overview(Request $request, GetWorkAssignmentRefundOverviewQuery $query): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::MembersEdit->value);
        $overview = $query->execute($this->year($request));

        return new JsonResponse([
            'year' => $overview->year,
            'selectableYears' => $overview->selectableYears,
            'defaultExecutionDate' => $overview->defaultExecutionDate,
            'invalid' => array_map($this->entryToArray(...), $overview->invalid),
            'valid' => array_map($this->entryToArray(...), $overview->valid),
            'withoutAmount' => array_map($this->entryToArray(...), $overview->withoutAmount),
            'obstacleCategories' => $overview->obstacleCategories,
        ]);
    }

    #[Route('/api/admin/v1/work-assignment-refunds/{primaryMemberNumber}', name: 'api_admin_work_assignment_refund_detail', methods: ['GET'])]
    public function detail(string $primaryMemberNumber, Request $request, GetWorkAssignmentRefundDetailQuery $query): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::MembersEdit->value);
        $detail = $query->execute($primaryMemberNumber, $this->year($request));

        return new JsonResponse([
            'year' => $detail->year,
            'defaultExecutionDate' => $detail->defaultExecutionDate,
            'entry' => $this->entryToArray($detail->entry),
            'bankName' => $detail->bankName,
            'creditPerHourCents' => $detail->creditPerHourCents,
            'totalSurchargeCents' => $detail->totalSurchargeCents,
            'clubAccount' => [
                'name' => $detail->clubAccount->name,
                'iban' => $detail->clubAccount->iban,
                'bic' => $detail->clubAccount->bic,
            ],
            'members' => array_map(static fn (WorkAssignmentRefundMemberResponse $member): array => [
                'memberId' => $member->memberId,
                'memberNumber' => $member->memberNumber,
                'name' => $member->name,
                'surchargeCents' => $member->surchargeCents,
                'workedMinutes' => $member->workedMinutes,
                'participations' => array_map(static fn (WorkAssignmentParticipation $participation): array => [
                    'eventTitle' => $participation->eventTitle,
                    'eventDate' => $participation->eventDate,
                    'eventTime' => $participation->eventTime,
                    'minutes' => $participation->minutes,
                ], $member->participations),
            ], $detail->members),
            'refunds' => array_map(static fn (WorkAssignmentRefundRecord $record): array => [
                'amountCents' => $record->amountCents,
                'exportedAt' => $record->exportedAt->format(\DateTimeInterface::ATOM),
            ], $detail->refunds),
        ]);
    }

    // POST statt GET: der optionale PIN landet im JSON-Body statt in der Query-String.
    #[Route('/api/admin/v1/work-assignment-refunds/export', name: 'api_admin_work_assignment_refund_export', methods: ['POST'])]
    public function export(Request $request, ExportWorkAssignmentRefundsUseCase $useCase, VerifyPinUseCase $verifyPin): Response
    {
        $this->denyAccessUnlessGranted(Permission::MembersEdit->value);
        $payload = $request->getPayload();
        $pin = $payload->getString('pin');
        $verifyPin->execute(ProtectedAction::MembersDirectDebitExport, $pin === '' ? null : $pin);

        $primaryMemberNumbers = [];
        foreach ($payload->all('primaryMemberNumbers') as $primaryMemberNumber) {
            if (!is_string($primaryMemberNumber)) {
                throw new BadRequestHttpException('Die Haushaltsauswahl ist ungültig.');
            }
            $primaryMemberNumbers[] = $primaryMemberNumber;
        }

        return $this->fileResponse($useCase->execute(new ExportWorkAssignmentRefundsRequest(
            year: $payload->getInt('year'),
            executionDate: $this->executionDate($payload->getString('executionDate')),
            primaryMemberNumbers: $primaryMemberNumbers,
        )));
    }

    private function year(Request $request): ?int
    {
        $year = $request->query->getString('year');
        if ($year !== '' && preg_match('/^\d{4}$/', $year) !== 1) {
            throw new BadRequestHttpException('Das Beitragsjahr ist ungültig.');
        }

        return $year === '' ? null : (int) $year;
    }

    // POST statt GET: der optionale PIN landet im JSON-Body statt in der Query-String.
    #[Route('/api/admin/v1/work-assignment-refunds/{primaryMemberNumber}/export', name: 'api_admin_work_assignment_refund_export_household', methods: ['POST'])]
    public function exportHousehold(string $primaryMemberNumber, Request $request, ExportHouseholdWorkAssignmentRefundUseCase $useCase, VerifyPinUseCase $verifyPin): Response
    {
        $this->denyAccessUnlessGranted(Permission::MembersEdit->value);
        $payload = $request->getPayload();
        $pin = $payload->getString('pin');
        $verifyPin->execute(ProtectedAction::MembersDirectDebitExport, $pin === '' ? null : $pin);

        return $this->fileResponse($useCase->execute(new ExportHouseholdWorkAssignmentRefundRequest(
            primaryMemberNumber: $primaryMemberNumber,
            year: $payload->getInt('year'),
            executionDate: $this->executionDate($payload->getString('executionDate')),
        )));
    }

    private function executionDate(string $value): \DateTimeImmutable
    {
        return \DateTimeImmutable::createFromFormat('!Y-m-d', $value)
            ?: throw new BadRequestHttpException('Das Ausführungsdatum ist ungültig.');
    }

    private function fileResponse(WorkAssignmentRefundFileResponse $file): Response
    {
        $response = new Response($file->content);
        $response->headers->set('Content-Type', 'application/xml; charset=UTF-8');
        $response->headers->set('Content-Disposition', sprintf('attachment; filename="%s"', $file->fileName));

        return $response;
    }

    /**
     * @return array<string, string|int|list<array{kind: string, message: string}>|null>
     */
    private function entryToArray(WorkAssignmentRefundOverviewEntryResponse $entry): array
    {
        return [
            'primaryMemberNumber' => $entry->primaryMemberNumber,
            'payerId' => $entry->payerId,
            'memberNumber' => $entry->memberNumber,
            'lastName' => $entry->lastName,
            'firstName' => $entry->firstName,
            'recipientName' => $entry->recipientName,
            'iban' => $entry->iban,
            'liableMemberCount' => $entry->liableMemberCount,
            'workedMinutes' => $entry->workedMinutes,
            'requiredHoursPerAssignment' => $entry->requiredHoursPerAssignment,
            'creditCents' => $entry->creditCents,
            'refundedCents' => $entry->refundedCents,
            'amountCents' => $entry->amountCents,
            'obstacles' => $entry->obstacles,
        ];
    }
}
