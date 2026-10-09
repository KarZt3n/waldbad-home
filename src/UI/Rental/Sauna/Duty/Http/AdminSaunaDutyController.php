<?php

namespace App\UI\Rental\Sauna\Duty\Http;

use App\Logic\Rental\Sauna\Duty\Query\GetSaunaDutyRosterQuery;
use App\Logic\Rental\Sauna\Duty\Query\SearchSaunaDutyCandidatesQuery;
use App\Logic\Rental\Sauna\Duty\UseCase\AssignSaunaDutyUseCase;
use App\Logic\Rental\Sauna\Duty\UseCase\RemoveSaunaDutyUseCase;
use App\UI\IdentityAccess\Security\Permission;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/admin/v1/sauna-duties')]
final class AdminSaunaDutyController extends AbstractController
{
    public function __construct(private readonly SaunaDutyResponseFactory $responseFactory)
    {
    }

    /** Dienstplan für `days` Tage ab `from` (Y-m-d). */
    #[Route('', name: 'api_admin_sauna_duty_roster', methods: ['GET'])]
    public function roster(Request $request, GetSaunaDutyRosterQuery $query): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::RentalSaunaView->value);
        $from = $this->date($request->query->getString('from'));
        $days = $request->query->getInt('days', 1);

        return new JsonResponse($this->responseFactory->roster($query->execute($from, $days)));
    }

    /**
     * Eigener, schlanker Such-Endpunkt (wie `member-candidates` der Helferanmeldungen): benötigt
     * bewusst nicht `Permission::MembersView`, damit auch Sauna-Verantwortliche eintragen können.
     */
    #[Route('/candidates', name: 'api_admin_sauna_duty_candidates', methods: ['GET'])]
    public function candidates(Request $request, SearchSaunaDutyCandidatesQuery $query): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::RentalSaunaEdit->value);

        return new JsonResponse($this->responseFactory->candidates($query->execute(trim($request->query->getString('search')))));
    }

    #[Route('', name: 'api_admin_sauna_duty_assign', methods: ['POST'])]
    public function assign(Request $request, AssignSaunaDutyUseCase $useCase): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::RentalSaunaEdit->value);
        $data = $request->getPayload();
        $time = trim($data->getString('time'));
        $memberId = trim($data->getString('memberId'));
        if ($time === '' || $memberId === '') {
            throw new BadRequestHttpException('Datum, Uhrzeit und Person sind erforderlich.');
        }

        return new JsonResponse(
            SaunaDutyResponseFactory::duty($useCase->execute($this->date($data->getString('date')), $time, $memberId)),
            JsonResponse::HTTP_CREATED,
        );
    }

    #[Route('/{id}', name: 'api_admin_sauna_duty_remove', methods: ['DELETE'])]
    public function remove(string $id, RemoveSaunaDutyUseCase $useCase): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::RentalSaunaEdit->value);
        $useCase->execute($id);

        return new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT);
    }

    private function date(string $raw): \DateTimeImmutable
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', trim($raw));

        return $date !== false ? $date : throw new BadRequestHttpException('Das Datum ist ungültig.');
    }
}
