<?php

namespace App\UI\Event\Schedule\Http;

use App\Logic\Event\Schedule\Model\EventScheduleKind;
use App\Logic\Event\Schedule\Query\ListCurrentYearEventSchedulesQuery;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/public/v1/events')]
readonly class PublicEventScheduleController
{
    public function __construct(private EventScheduleResponseFactory $responseFactory)
    {
    }

    #[Route('', name: 'api_public_event_schedule_list', methods: ['GET'])]
    public function list(Request $request, ListCurrentYearEventSchedulesQuery $query): JsonResponse
    {
        try {
            $kind = EventScheduleKind::from($request->query->getString('kind', EventScheduleKind::Event->value));
        } catch (\ValueError) {
            throw new BadRequestHttpException('Die Art der Veranstaltung ist ungültig.');
        }

        return new JsonResponse($this->responseFactory->collection($query->execute($kind)));
    }
}
