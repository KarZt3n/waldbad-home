<?php

namespace App\UI\Event\Schedule\Http;

use App\Logic\Common\IdentifierGeneratorInterface;
use App\Logic\Content\Page\HtmlSanitizerInterface;
use App\Logic\Event\Schedule\Dto\CreateEventScheduleRequest;
use App\Logic\Event\Schedule\Dto\UpdateEventScheduleRequest;
use App\Logic\Event\Schedule\Model\EventScheduleActivity;
use App\Logic\Event\Schedule\Model\EventScheduleCallToAction;
use App\Logic\Event\Schedule\Model\EventScheduleKind;
use Symfony\Component\HttpFoundation\InputBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

readonly class EventScheduleRequestMapper
{
    public function __construct(
        private HtmlSanitizerInterface $htmlSanitizer,
        private IdentifierGeneratorInterface $identifierGenerator,
    ) {
    }

    public function create(Request $request): CreateEventScheduleRequest
    {
        $data = $request->getPayload();

        return new CreateEventScheduleRequest(
            kind: $this->kind($data),
            title: $this->requiredString($data, 'title'),
            date: $this->requiredString($data, 'date'),
            time: $this->requiredString($data, 'time'),
            content: $this->content($data),
            mediaUrl: $this->nullableString($data, 'mediaUrl'),
            mediaAlt: $this->nullableString($data, 'mediaAlt'),
            mediaSource: $this->nullableString($data, 'mediaSource'),
            layout: $this->nullableString($data, 'layout'),
            imageWidthPercent: $data->get('imageWidthPercent') === null ? null : $data->getInt('imageWidthPercent'),
            verticalAlignment: $this->nullableString($data, 'verticalAlignment'),
            textAlignment: $this->nullableString($data, 'textAlignment'),
            imageFit: $this->nullableString($data, 'imageFit'),
            helpEnabled: $data->getBoolean('helpEnabled'),
            helpButtonLabel: $this->nullableString($data, 'helpButtonLabel'),
            visible: $data->getBoolean('visible', true),
            activities: $this->activities($data),
            callToActions: $this->callToActions($data),
        );
    }

    public function update(string $id, Request $request): UpdateEventScheduleRequest
    {
        $data = $request->getPayload();

        return new UpdateEventScheduleRequest(
            id: $id,
            title: $this->requiredString($data, 'title'),
            date: $this->requiredString($data, 'date'),
            time: $this->requiredString($data, 'time'),
            content: $this->content($data),
            mediaUrl: $this->nullableString($data, 'mediaUrl'),
            mediaAlt: $this->nullableString($data, 'mediaAlt'),
            mediaSource: $this->nullableString($data, 'mediaSource'),
            layout: $this->nullableString($data, 'layout'),
            imageWidthPercent: $data->get('imageWidthPercent') === null ? null : $data->getInt('imageWidthPercent'),
            verticalAlignment: $this->nullableString($data, 'verticalAlignment'),
            textAlignment: $this->nullableString($data, 'textAlignment'),
            imageFit: $this->nullableString($data, 'imageFit'),
            helpEnabled: $data->getBoolean('helpEnabled'),
            helpButtonLabel: $this->nullableString($data, 'helpButtonLabel'),
            visible: $data->getBoolean('visible', true),
            activities: $this->activities($data),
            callToActions: $this->callToActions($data),
        );
    }

    /** @param InputBag<string|int|float|bool|null> $data */
    private function kind(InputBag $data): EventScheduleKind
    {
        try {
            return EventScheduleKind::from($data->getString('kind'));
        } catch (\ValueError) {
            throw new BadRequestHttpException('Die Art der Veranstaltung ist ungültig.');
        }
    }

    /** @param InputBag<string|int|float|bool|null> $data */
    private function content(InputBag $data): string
    {
        return $this->htmlSanitizer->sanitize(trim($data->getString('content')));
    }

    /**
     * @param InputBag<string|int|float|bool|null> $data
     * @return list<EventScheduleActivity>
     */
    private function activities(InputBag $data): array
    {
        $activities = $data->all('activities');
        if (!array_is_list($activities)) {
            throw new BadRequestHttpException('Die Aktivitäten müssen als Liste angegeben werden.');
        }

        $mapped = [];
        foreach ($activities as $position => $activity) {
            if (!is_array($activity) || !is_string($activity['activityId'] ?? null) || !is_int($activity['requiredHelpers'] ?? null)) {
                throw new BadRequestHttpException('Eine Aktivität benötigt Kennung und Helferzahl.');
            }
            $time = $activity['time'] ?? null;
            $meetTime = $activity['meetTime'] ?? null;
            $meetPlace = $activity['meetPlace'] ?? null;
            $remark = $activity['remark'] ?? null;
            if (($time !== null && !is_string($time))
                || ($meetTime !== null && !is_string($meetTime))
                || ($meetPlace !== null && !is_string($meetPlace))
                || ($remark !== null && !is_string($remark))) {
                throw new BadRequestHttpException('Die Zusatzangaben einer Aktivität sind ungültig.');
            }
            $mapped[] = new EventScheduleActivity(
                id: $this->identifierGenerator->generate(),
                position: $position,
                activityId: trim($activity['activityId']),
                requiredHelpers: $activity['requiredHelpers'],
                time: $time === null || trim($time) === '' ? null : trim($time),
                meetTime: $meetTime === null || trim($meetTime) === '' ? null : trim($meetTime),
                meetPlace: $meetPlace === null || trim($meetPlace) === '' ? null : trim($meetPlace),
                remark: $remark === null || trim($remark) === '' ? null : trim($remark),
            );
        }

        return $mapped;
    }

    /**
     * @param InputBag<string|int|float|bool|null> $data
     * @return list<EventScheduleCallToAction>
     */
    private function callToActions(InputBag $data): array
    {
        $callToActions = $data->all('callToActions');
        if (!array_is_list($callToActions)) {
            throw new BadRequestHttpException('Die Aktionsbuttons müssen als Liste angegeben werden.');
        }

        $mapped = [];
        foreach ($callToActions as $position => $callToAction) {
            $label = is_array($callToAction) ? ($callToAction['label'] ?? null) : null;
            $url = is_array($callToAction) ? ($callToAction['url'] ?? null) : null;
            $pageId = is_array($callToAction) ? ($callToAction['pageId'] ?? null) : null;
            if (!is_array($callToAction)
                || !is_string($label)
                || ($url !== null && !is_string($url))
                || ($pageId !== null && !is_string($pageId))) {
                throw new BadRequestHttpException('Ein Aktionsbutton benötigt Beschriftung und ein gültiges Ziel.');
            }
            $mapped[] = new EventScheduleCallToAction(
                id: $this->identifierGenerator->generate(),
                position: $position,
                label: trim(strip_tags($label)),
                url: $url === null || trim($url) === '' ? null : trim($url),
                pageId: $pageId === null || trim($pageId) === '' ? null : trim($pageId),
            );
        }

        return $mapped;
    }

    /** @param InputBag<string|int|float|bool|null> $data */
    private function requiredString(InputBag $data, string $key): string
    {
        $value = trim($data->getString($key));
        if ($value === '') {
            throw new BadRequestHttpException(sprintf('Das Feld "%s" ist erforderlich.', $key));
        }

        return $value;
    }

    /** @param InputBag<string|int|float|bool|null> $data */
    private function nullableString(InputBag $data, string $key): ?string
    {
        $value = trim($data->getString($key));

        return $value === '' ? null : $value;
    }
}
