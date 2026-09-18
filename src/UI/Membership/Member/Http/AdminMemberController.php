<?php

namespace App\UI\Membership\Member\Http;

use App\Logic\Membership\Member\Dto\ImportMembersRequest;
use App\Logic\Membership\Member\Dto\ImportRowError;
use App\Logic\Membership\Member\Dto\MemberRecalculationError;
use App\Logic\Membership\Member\Query\GetMemberHouseholdQuery;
use App\Logic\Membership\Member\Query\GetMemberQuery;
use App\Logic\Membership\Member\Query\ListMembersQuery;
use App\Logic\Membership\Member\EmailConsent\UseCase\SendMemberEmailConsentRequestUseCase;
use App\Logic\Membership\Member\UseCase\AddMemberRemarkUseCase;
use App\Logic\Membership\Member\UseCase\CreateMemberUseCase;
use App\Logic\Membership\Member\UseCase\DeleteMemberUseCase;
use App\Logic\Membership\Member\UseCase\ImportMembersUseCase;
use App\Logic\Membership\Member\UseCase\RecalculateAllMemberContributionsUseCase;
use App\Logic\Membership\Member\UseCase\RecalculateMemberContributionUseCase;
use App\Logic\Membership\Member\UseCase\UpdateMemberUseCase;
use App\Logic\Settings\Pin\Model\ProtectedAction;
use App\Logic\Settings\Pin\Query\GetPinSettingsQuery;
use App\Logic\Settings\Pin\UseCase\VerifyPinUseCase;
use App\UI\IdentityAccess\Security\AuthenticatedUser;
use App\UI\IdentityAccess\Security\Permission;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api/admin/v1/members')]
class AdminMemberController extends AbstractController
{
    private const array EXPORT_FORMATS = ['csv', 'json', 'xml'];

    public function __construct(
        private readonly MemberResponseFactory $responseFactory,
        private readonly MemberRequestMapper $requestMapper,
        private readonly MemberExportFormatter $exportFormatter,
        private readonly MemberImportFileParser $importFileParser,
        private readonly MemberExportZipArchive $zipArchive,
    ) {
    }

    /**
     * Ob „Mitgliederexport/-import" gerade per PIN geschützt ist — bestimmt, ob das Export-ZIP
     * verschlüsselt wird bzw. beim Import ein Passwort zum Entpacken nötig ist.
     */
    private function memberExportIsPinProtected(GetPinSettingsQuery $pinSettingsQuery): bool
    {
        return in_array(ProtectedAction::MembersExport->value, $pinSettingsQuery->execute()->protectedActions, true);
    }

    #[Route('', name: 'api_admin_member_list', methods: ['GET'])]
    public function list(Request $request, ListMembersQuery $query): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::MembersView->value);
        $search = trim((string) $request->query->get('search', ''));

        return new JsonResponse($this->responseFactory->collection($query->execute($search === '' ? null : $search)));
    }

    // POST statt GET: der PIN (siehe unten) landet damit im JSON-Body statt in der Query-String
    // (Logs/Browser-Verlauf).
    #[Route('/export', name: 'api_admin_member_export', methods: ['POST'])]
    public function export(Request $request, ListMembersQuery $query, VerifyPinUseCase $verifyPin, GetPinSettingsQuery $pinSettingsQuery): Response
    {
        $this->denyAccessUnlessGranted(Permission::MembersView->value);
        $payload = $request->getPayload();
        $format = (string) $payload->getString('format', 'json');
        if (!in_array($format, self::EXPORT_FORMATS, true)) {
            throw new BadRequestHttpException('Das Export-Format wird nicht unterstützt.');
        }

        // Zusätzlich zur regulären Berechtigung optional per PIN geschützt (siehe Modul
        // „Einstellungen" → PIN-Schutz). Ist der Schutz für „Mitgliederexport/-import" aktiviert,
        // wird der eingegebene (und hier verifizierte) PIN direkt als ZIP-Passwort verwendet —
        // derselbe PIN entsperrt das Archiv beim Import wieder (siehe `import()` unten). Ist der
        // Schutz nicht aktiviert, ist die Prüfung ein No-op und das ZIP bleibt unverschlüsselt.
        $submittedPin = $payload->getString('pin', '');
        $submittedPin = $submittedPin === '' ? null : $submittedPin;
        $verifyPin->execute(ProtectedAction::MembersExport, $submittedPin);
        $isProtected = $this->memberExportIsPinProtected($pinSettingsQuery);

        $rows = $this->responseFactory->collection($query->execute(null))['items'];
        $content = match ($format) {
            'csv' => $this->exportFormatter->toCsv($rows),
            'json' => $this->exportFormatter->toJson($rows),
            'xml' => $this->exportFormatter->toXml($rows),
        };
        $zipContent = $this->zipArchive->build($content, $format, $isProtected ? $submittedPin : null);

        $response = new Response($zipContent);
        $response->headers->set('Content-Type', 'application/zip');
        $filename = sprintf('mitglieder-%s.zip', (new \DateTimeImmutable())->format('dmY_His'));
        $response->headers->set('Content-Disposition', sprintf('attachment; filename="%s"', $filename));

        return $response;
    }

    /**
     * Berechnet den Beitrag für alle Mitglieder neu (z. B. nach Änderungen an den
     * Beitragssätzen). Ein fehlender Beitragssatz für einzelne Mitglieder bricht den Lauf nicht
     * ab; betroffene Datensätze werden übersprungen und in `errors` gemeldet.
     */
    #[Route('/recalculate-contributions', name: 'api_admin_member_recalculate_all_contributions', methods: ['POST'])]
    public function recalculateAllContributions(Request $request, RecalculateAllMemberContributionsUseCase $useCase): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::MembersEdit->value);
        // Stichtag für die Alters-/Kategorieermittlung (siehe `RecalculateAllMemberContributionsUseCase`);
        // ohne Angabe berechnet die Use Case selbst ab „jetzt“.
        $rawAt = trim($request->getPayload()->getString('at', ''));
        $at = null;
        if ($rawAt !== '') {
            $at = \DateTimeImmutable::createFromFormat('!Y-m-d', $rawAt);
            if ($at === false) {
                throw new BadRequestHttpException('Der Stichtag ist ungültig.');
            }
        }
        $result = $useCase->execute($at);

        return new JsonResponse([
            'updated' => $result->updated,
            'errors' => array_map(
                static fn (MemberRecalculationError $error): array => [
                    'memberNumber' => $error->memberNumber,
                    'message' => $error->message,
                ],
                $result->errors,
            ),
        ]);
    }

    #[Route('/import', name: 'api_admin_member_import', methods: ['POST'])]
    public function import(Request $request, ImportMembersUseCase $useCase, VerifyPinUseCase $verifyPin, GetPinSettingsQuery $pinSettingsQuery): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::MembersEdit->value);
        $file = $request->files->get('file');
        if (!$file instanceof UploadedFile) {
            throw new BadRequestHttpException('Es wurde keine Datei übermittelt.');
        }
        $zipContent = file_get_contents($file->getPathname());
        if ($zipContent === false) {
            throw new BadRequestHttpException('Die Datei konnte nicht gelesen werden.');
        }

        // Derselbe PIN, mit dem das Export-ZIP verschlüsselt wurde (siehe `export()` oben) — bei
        // aktiviertem Schutz erst hier verifiziert, bevor überhaupt versucht wird, das Archiv damit
        // zu entpacken.
        $submittedPin = (string) $request->request->get('pin', '');
        $submittedPin = $submittedPin === '' ? null : $submittedPin;
        $verifyPin->execute(ProtectedAction::MembersExport, $submittedPin);
        $isProtected = $this->memberExportIsPinProtected($pinSettingsQuery);

        ['content' => $content, 'format' => $format] = $this->zipArchive->extract(
            $zipContent,
            $isProtected ? $submittedPin : null,
            self::EXPORT_FORMATS,
        );

        $rawRows = $this->importFileParser->parse($content, $format);
        $rows = [];
        $parseErrors = [];
        foreach ($rawRows as $index => $rawRow) {
            try {
                $rows[] = $this->requestMapper->fromImportRow($rawRow);
            } catch (BadRequestHttpException $exception) {
                $parseErrors[] = new ImportRowError($index + 1, $exception->getMessage());
            }
        }

        $result = $useCase->execute(new ImportMembersRequest($rows));

        return new JsonResponse([
            'created' => $result->created,
            'updated' => $result->updated,
            'errors' => array_map(
                static fn (ImportRowError $error): array => ['row' => $error->rowNumber, 'message' => $error->message],
                [...$parseErrors, ...$result->errors],
            ),
        ]);
    }

    #[Route('', name: 'api_admin_member_create', methods: ['POST'])]
    public function create(Request $request, CreateMemberUseCase $useCase): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::MembersEdit->value);
        $createRequest = $this->requestMapper->create($request->getPayload()->all());

        return new JsonResponse($this->responseFactory->member($useCase->execute($createRequest)), JsonResponse::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'api_admin_member_get', methods: ['GET'], requirements: ['id' => '[0-9a-fA-F-]{36}'])]
    public function get(string $id, GetMemberQuery $query): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::MembersView->value);

        return new JsonResponse($this->responseFactory->member($query->execute($id)));
    }

    #[Route('/{id}/household', name: 'api_admin_member_household', methods: ['GET'], requirements: ['id' => '[0-9a-fA-F-]{36}'])]
    public function household(string $id, GetMemberHouseholdQuery $query): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::MembersView->value);

        return new JsonResponse($this->responseFactory->household($query->execute($id)));
    }

    #[Route('/{id}', name: 'api_admin_member_update', methods: ['PUT'], requirements: ['id' => '[0-9a-fA-F-]{36}'])]
    public function update(string $id, Request $request, UpdateMemberUseCase $useCase): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::MembersEdit->value);
        $data = $request->getPayload();
        $version = $data->getInt('version');
        $updateRequest = $this->requestMapper->update($id, $version, $data->all());

        return new JsonResponse($this->responseFactory->member($useCase->execute($updateRequest)));
    }

    #[Route('/{id}', name: 'api_admin_member_delete', methods: ['DELETE'], requirements: ['id' => '[0-9a-fA-F-]{36}'])]
    public function delete(string $id, Request $request, DeleteMemberUseCase $useCase, VerifyPinUseCase $verifyPin): Response
    {
        $this->denyAccessUnlessGranted(Permission::MembersEdit->value);
        // Zusätzlich zur regulären Berechtigung optional per PIN geschützt (siehe Modul
        // „Einstellungen“ → PIN-Schutz). Ist der Schutz für „Mitglied löschen“ nicht aktiviert, ist
        // dieser Aufruf ein No-op.
        $submittedPin = $request->getPayload()->getString('pin', '');
        $verifyPin->execute(ProtectedAction::MembersDelete, $submittedPin === '' ? null : $submittedPin);
        $useCase->execute($id);

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/{id}/remarks', name: 'api_admin_member_add_remark', methods: ['POST'], requirements: ['id' => '[0-9a-fA-F-]{36}'])]
    public function addRemark(
        string $id,
        Request $request,
        AddMemberRemarkUseCase $useCase,
        #[CurrentUser] AuthenticatedUser $user,
    ): JsonResponse {
        $this->denyAccessUnlessGranted(Permission::MembersEdit->value);
        $remarkRequest = $this->requestMapper->addRemark($id, $request, $user->getDisplayName());

        return new JsonResponse($this->responseFactory->member($useCase->execute($remarkRequest)));
    }

    #[Route('/{id}/recalculate-contribution', name: 'api_admin_member_recalculate_contribution', methods: ['POST'], requirements: ['id' => '[0-9a-fA-F-]{36}'])]
    public function recalculateContribution(string $id, RecalculateMemberContributionUseCase $useCase): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::MembersEdit->value);

        return new JsonResponse($this->responseFactory->member($useCase->execute($id)));
    }

    /**
     * Verschickt den Doppel-Opt-in-Bestätigungslink für die E-Mail-Einwilligung an die hinterlegte
     * E-Mail-Adresse des Mitglieds (siehe `SendMemberEmailConsentRequestUseCase`) — die Einwilligung
     * selbst gilt erst als erteilt, wenn das Mitglied den Link anklickt.
     */
    #[Route('/{id}/email-consent-requests', name: 'api_admin_member_send_email_consent_request', methods: ['POST'], requirements: ['id' => '[0-9a-fA-F-]{36}'])]
    public function sendEmailConsentRequest(string $id, SendMemberEmailConsentRequestUseCase $useCase): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::MembersEdit->value);

        return new JsonResponse($this->responseFactory->member($useCase->execute($id)));
    }
}
