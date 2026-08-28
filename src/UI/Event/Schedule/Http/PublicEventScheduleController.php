<?php

namespace App\UI\Event\Schedule\Http;

use App\Logic\Event\Schedule\Model\EventScheduleKind;
use App\Logic\Event\Schedule\Query\GetNextEventScheduleQuery;
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
        return new JsonResponse($this->responseFactory->collection($query->execute($this->kind($request))));
    }

    #[Route('/next', name: 'api_public_event_schedule_next', methods: ['GET'])]
    public function next(Request $request, GetNextEventScheduleQuery $query): JsonResponse
    {
        $schedule = $query->execute($this->nextKind($request));

        return new JsonResponse(['item' => $schedule === null ? null : $this->responseFactory->schedule($schedule)]);
    }

    private function kind(Request $request): EventScheduleKind
    {
        try {
            return EventScheduleKind::from($request->query->getString('kind', EventScheduleKind::Event->value));
        } catch (\ValueError) {
            throw new BadRequestHttpException('Die Art der Veranstaltung ist ungültig.');
        }
    }

    /**
     * @return EventScheduleKind|null Null steht für „egal, Veranstaltung oder Arbeitseinsatz“.
     */
    private function nextKind(Request $request): ?EventScheduleKind
    {
        $value = $request->query->getString('kind', EventScheduleKind::Event->value);
        if ($value === 'any') {
            return null;
        }

        try {
            return EventScheduleKind::from($value);
        } catch (\ValueError) {
            throw new BadRequestHttpException('Die Art der Veranstaltung ist ungültig.');
        }
    }
}
