<?php

namespace App\UI\Event\Activity\Http;

use App\Logic\Event\Activity\Dto\EventActivityAvailabilityResponse;
use App\Logic\Event\Activity\Query\ListEventActivityAvailabilityQuery;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/public/v1/event-activities')]
readonly class PublicEventActivityController
{
    #[Route('/{eventIdentifier}', name: 'api_public_event_activity_list', methods: ['GET'])]
    public function list(string $eventIdentifier, ListEventActivityAvailabilityQuery $query): JsonResponse
    {
        return new JsonResponse(['items' => array_map(
            static fn (EventActivityAvailabilityResponse $activity): array => [
                'id' => $activity->id,
                'name' => $activity->name,
                'description' => $activity->description,
                'requiredHelpers' => $activity->requiredHelpers,
                'registeredHelpers' => $activity->registeredHelpers,
            ],
            $query->execute($eventIdentifier),
        )]);
    }
}
