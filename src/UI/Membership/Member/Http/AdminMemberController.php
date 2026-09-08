<?php

namespace App\UI\Membership\Member\Http;

use App\Logic\Membership\Member\Dto\ImportMembersRequest;
use App\Logic\Membership\Member\Dto\ImportRowError;
use App\Logic\Membership\Member\Dto\MemberRecalculationError;
use App\Logic\Membership\Member\Query\GetMemberHouseholdQuery;
use App\Logic\Membership\Member\Query\GetMemberQuery;
use App\Logic\Membership\Member\Query\ListMembersQuery;
use App\Logic\Membership\Member\UseCase\AddMemberRemarkUseCase;
use App\Logic\Membership\Member\UseCase\CreateMemberUseCase;
use App\Logic\Membership\Member\UseCase\DeleteMemberUseCase;
use App\Logic\Membership\Member\UseCase\ImportMembersUseCase;
use App\Logic\Membership\Member\UseCase\RecalculateAllMemberContributionsUseCase;
use App\Logic\Membership\Member\UseCase\RecalculateMemberContributionUseCase;
use App\Logic\Membership\Member\UseCase\UpdateMemberUseCase;
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
    ) {
    }

    #[Route('', name: 'api_admin_member_list', methods: ['GET'])]
    public function list(Request $request, ListMembersQuery $query): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::MembersView->value);
        $search = trim((string) $request->query->get('search', ''));

        return new JsonResponse($this->responseFactory->collection($query->execute($search === '' ? null : $search)));
    }

    #[Route('/export', name: 'api_admin_member_export', methods: ['GET'])]
    public function export(Request $request, ListMembersQuery $query): Response
    {
        $this->denyAccessUnlessGranted(Permission::MembersView->value);
        $format = (string) $request->query->get('format', 'csv');
        if (!in_array($format, self::EXPORT_FORMATS, true)) {
            throw new BadRequestHttpException('Das Export-Format wird nicht unterstützt.');
        }

        $rows = $this->responseFactory->collection($query->execute(null))['items'];
        [$content, $contentType] = match ($format) {
            'csv' => [$this->exportFormatter->toCsv($rows), 'text/csv; charset=UTF-8'],
            'json' => [$this->exportFormatter->toJson($rows), 'application/json; charset=UTF-8'],
            'xml' => [$this->exportFormatter->toXml($rows), 'application/xml; charset=UTF-8'],
        };

        $response = new Response($content);
        $response->headers->set('Content-Type', $contentType);
        $response->headers->set('Content-Disposition', sprintf('attachment; filename="mitglieder.%s"', $format));

        return $response;
    }

    /**
     * Berechnet den Beitrag für alle Mitglieder neu (z. B. nach Änderungen an den
     * Beitragssätzen). Ein fehlender Beitragssatz für einzelne Mitglieder bricht den Lauf nicht
     * ab; betroffene Datensätze werden übersprungen und in `errors` gemeldet.
     */
    #[Route('/recalculate-contributions', name: 'api_admin_member_recalculate_all_contributions', methods: ['POST'])]
    public function recalculateAllContributions(RecalculateAllMemberContributionsUseCase $useCase): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::MembersEdit->value);
        $result = $useCase->execute();

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
    public function import(Request $request, ImportMembersUseCase $useCase): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::MembersEdit->value);
        $format = (string) $request->request->get('format', 'csv');
        if (!in_array($format, self::EXPORT_FORMATS, true)) {
            throw new BadRequestHttpException('Das Import-Format wird nicht unterstützt.');
        }
        $file = $request->files->get('file');
        if (!$file instanceof UploadedFile) {
            throw new BadRequestHttpException('Es wurde keine Datei übermittelt.');
        }
        $content = file_get_contents($file->getPathname());
        if ($content === false) {
            throw new BadRequestHttpException('Die Datei konnte nicht gelesen werden.');
        }

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
    public function delete(string $id, DeleteMemberUseCase $useCase): Response
    {
        $this->denyAccessUnlessGranted(Permission::MembersEdit->value);
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
}
