<?php

namespace App\UI\Event\HelpRequest\Http;

use App\Logic\Event\HelpRequest\Query\ListEventHelpRequestsQuery;
use App\Logic\Event\HelpRequest\Dto\ParticipationIntervalInput;
use App\Logic\Event\HelpRequest\UseCase\RecordEventHelpParticipationUseCase;
use App\UI\IdentityAccess\Security\Permission;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/admin/v1/event-help-requests')]
class AdminEventHelpRequestController extends AbstractController
{
    public function __construct(private readonly EventHelpRequestResponseFactory $responseFactory)
    {
    }

    #[Route('', name: 'api_admin_event_help_list', methods: ['GET'])]
    public function list(ListEventHelpRequestsQuery $query): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::EventHelpManage->value);

        return new JsonResponse($this->responseFactory->collection($query->execute()));
    }

    #[Route('/{id}/participation', name: 'api_admin_event_help_participation', methods: ['POST'])]
    public function participation(string $id, Request $request, RecordEventHelpParticipationUseCase $useCase): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::EventHelpManage->value);
        $data = $request->getPayload();
        $participated = $data->getBoolean('participated');
        $intervals = [];
        if ($participated) {
            $rawIntervals = $data->all('intervals');
            if (!array_is_list($rawIntervals) || $rawIntervals === [] || count($rawIntervals) > 10) {
                throw new BadRequestHttpException('Es müssen zwischen einem und zehn Hilfezeiträume angegeben werden.');
            }
            foreach ($rawIntervals as $rawInterval) {
                if (!is_array($rawInterval)
                    || !is_string($rawInterval['fromTime'] ?? null)
                    || !is_string($rawInterval['toTime'] ?? null)) {
                    throw new BadRequestHttpException('Jeder Hilfezeitraum benötigt eine Von- und Bis-Uhrzeit.');
                }
                $intervals[] = new ParticipationIntervalInput(
                    fromTime: trim($rawInterval['fromTime']),
                    toTime: trim($rawInterval['toTime']),
                );
            }
        }

        return new JsonResponse($this->responseFactory->request($useCase->execute($id, $participated, $intervals)));
    }
}
