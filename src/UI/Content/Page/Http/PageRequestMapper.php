<?php

namespace App\UI\Content\Page\Http;

use App\Logic\Content\Page\Dto\CreatePageRequest;
use App\Logic\Content\Page\Dto\UpdatePageRequest;
use App\Logic\Content\Page\HtmlSanitizerInterface;
use App\Logic\Content\Page\Model\ContentBlock;
use App\Logic\Content\Page\Model\ContentBlockType;
use App\Logic\Content\Page\Model\EventActivityAssignment;
use Symfony\Component\HttpFoundation\InputBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

readonly class PageRequestMapper
{
    public function __construct(private HtmlSanitizerInterface $htmlSanitizer)
    {
    }

    public function create(Request $request): CreatePageRequest
    {
        $data = $request->getPayload();

        return new CreatePageRequest(
            title: $this->requiredString($data, 'title'),
            slug: $this->requiredString($data, 'slug'),
            navigationLabel: $this->requiredString($data, 'navigationLabel'),
            parentId: $this->nullableString($data, 'parentId'),
            blocks: $this->blocks($data),
            visible: $data->getBoolean('visible', true),
            showInNavigation: $data->getBoolean('showInNavigation', true),
            navigationPosition: $data->getInt('navigationPosition', 0),
            seoTitle: $this->nullableString($data, 'seoTitle'),
            seoDescription: $this->nullableString($data, 'seoDescription'),
        );
    }

    public function update(string $id, Request $request): UpdatePageRequest
    {
        $data = $request->getPayload();

        return new UpdatePageRequest(
            id: $id,
            title: $this->requiredString($data, 'title'),
            slug: $this->requiredString($data, 'slug'),
            navigationLabel: $this->requiredString($data, 'navigationLabel'),
            parentId: $this->nullableString($data, 'parentId'),
            blocks: $this->blocks($data),
            visible: $data->getBoolean('visible', true),
            showInNavigation: $data->getBoolean('showInNavigation', true),
            navigationPosition: $data->getInt('navigationPosition', 0),
            seoTitle: $this->nullableString($data, 'seoTitle'),
            seoDescription: $this->nullableString($data, 'seoDescription'),
            expectedVersion: $data->getInt('version'),
        );
    }

    /**
     * @param InputBag<string|int|float|bool|null> $data
     * @return list<ContentBlock>
     */
    private function blocks(InputBag $data): array
    {
        $blocks = $data->all('blocks');
        if (!array_is_list($blocks)) {
            throw new BadRequestHttpException('Das Feld "blocks" muss eine Liste sein.');
        }

        $mapped = [];
        foreach ($blocks as $block) {
            if (!is_array($block)) {
                throw new BadRequestHttpException('Jeder Inhaltsblock muss ein Objekt sein.');
            }

            $typeValue = $block['type'] ?? null;
            $contentValue = $block['content'] ?? '';
            $mediaUrl = $block['mediaUrl'] ?? null;
            $mediaAlt = $block['mediaAlt'] ?? null;
            $mediaSource = $block['mediaSource'] ?? null;
            $linkUrl = $block['linkUrl'] ?? null;
            $linkLabel = $block['linkLabel'] ?? null;
            $layout = $block['layout'] ?? null;
            $imageWidthPercent = $block['imageWidthPercent'] ?? null;
            $verticalAlignment = $block['verticalAlignment'] ?? null;
            $textAlignment = $block['textAlignment'] ?? null;
            $imageFit = $block['imageFit'] ?? null;
            $embeddedPageId = $block['embeddedPageId'] ?? null;
            $eventTitle = $block['eventTitle'] ?? null;
            $eventDate = $block['eventDate'] ?? null;
            $eventTime = $block['eventTime'] ?? null;
            $eventIdentifier = $block['eventIdentifier'] ?? null;
            $eventHelpEnabled = $block['eventHelpEnabled'] ?? $typeValue === ContentBlockType::Event->value;
            $eventHelpButtonLabel = $block['eventHelpButtonLabel'] ?? null;
            $eventActivities = $block['eventActivities'] ?? [];
            $extensionKey = $block['extensionKey'] ?? null;

            if (!is_string($typeValue) || trim($typeValue) === '') {
                throw new BadRequestHttpException('Jeder Inhaltsblock benötigt einen Typ.');
            }
            if (!is_string($contentValue)) {
                throw new BadRequestHttpException('Der Blockinhalt muss eine Zeichenkette sein.');
            }
            if ($mediaUrl !== null && !is_string($mediaUrl)) {
                throw new BadRequestHttpException('Die Medien-URL muss eine Zeichenkette sein.');
            }
            if ($mediaAlt !== null && !is_string($mediaAlt)) {
                throw new BadRequestHttpException('Der Alternativtext muss eine Zeichenkette sein.');
            }
            if ($mediaSource !== null && !is_string($mediaSource)) {
                throw new BadRequestHttpException('Die Bildquelle muss eine Zeichenkette sein.');
            }
            if ($linkUrl !== null && !is_string($linkUrl)) {
                throw new BadRequestHttpException('Die Link-URL muss eine Zeichenkette sein.');
            }
            if ($linkLabel !== null && !is_string($linkLabel)) {
                throw new BadRequestHttpException('Die Linkbeschriftung muss eine Zeichenkette sein.');
            }
            if ($layout !== null && !is_string($layout)) {
                throw new BadRequestHttpException('Das Blocklayout muss eine Zeichenkette sein.');
            }
            if ($imageWidthPercent !== null && !is_int($imageWidthPercent)) {
                throw new BadRequestHttpException('Die Bildbreite muss eine ganze Prozentzahl sein.');
            }
            if ($verticalAlignment !== null && !is_string($verticalAlignment)) {
                throw new BadRequestHttpException('Die vertikale Ausrichtung muss eine Zeichenkette sein.');
            }
            if ($textAlignment !== null && !is_string($textAlignment)) {
                throw new BadRequestHttpException('Die Textausrichtung muss eine Zeichenkette sein.');
            }
            if ($imageFit !== null && !is_string($imageFit)) {
                throw new BadRequestHttpException('Die Bilddarstellung muss eine Zeichenkette sein.');
            }
            if ($embeddedPageId !== null && !is_string($embeddedPageId)) {
                throw new BadRequestHttpException('Die eingebettete Seiten-ID muss eine Zeichenkette sein.');
            }
            if ($eventTitle !== null && !is_string($eventTitle)) {
                throw new BadRequestHttpException('Die Veranstaltungsüberschrift muss eine Zeichenkette sein.');
            }
            if ($eventDate !== null && !is_string($eventDate)) {
                throw new BadRequestHttpException('Das Veranstaltungsdatum muss eine Zeichenkette sein.');
            }
            if ($eventTime !== null && !is_string($eventTime)) {
                throw new BadRequestHttpException('Die Veranstaltungszeit muss eine Zeichenkette sein.');
            }
            if ($eventIdentifier !== null && !is_string($eventIdentifier)) {
                throw new BadRequestHttpException('Die Veranstaltungskennung muss eine Zeichenkette sein.');
            }
            if (!is_bool($eventHelpEnabled)) {
                throw new BadRequestHttpException('Die Helferanmeldung muss als Wahrheitswert angegeben werden.');
            }
            if ($eventHelpButtonLabel !== null && !is_string($eventHelpButtonLabel)) {
                throw new BadRequestHttpException('Die Beschriftung der Helferanmeldung muss eine Zeichenkette sein.');
            }
            if (!is_array($eventActivities) || !array_is_list($eventActivities)) {
                throw new BadRequestHttpException('Die Veranstaltungsaktivitäten müssen als Liste angegeben werden.');
            }
            $mappedActivities = [];
            foreach ($eventActivities as $activity) {
                if (!is_array($activity) || !is_string($activity['activityId'] ?? null) || !is_int($activity['requiredHelpers'] ?? null)) {
                    throw new BadRequestHttpException('Eine Veranstaltungsaktivität benötigt Kennung und Helferzahl.');
                }
                $mappedActivities[] = new EventActivityAssignment(trim($activity['activityId']), $activity['requiredHelpers']);
            }
            if ($extensionKey !== null && !is_string($extensionKey)) {
                throw new BadRequestHttpException('Der Erweiterungsschlüssel muss eine Zeichenkette sein.');
            }

            $type = trim($typeValue);
            try {
                $blockType = ContentBlockType::from($type);
            } catch (\ValueError) {
                throw new BadRequestHttpException(sprintf('Der Blocktyp "%s" ist ungültig.', $type));
            }

            $content = trim($contentValue);
            $content = in_array($blockType, [ContentBlockType::Heading, ContentBlockType::Image], true)
                ? $this->htmlSanitizer->sanitizeInline($content)
                : $this->htmlSanitizer->sanitize($content);
            $normalizedEventTitle = $eventTitle === null ? null : trim(strip_tags($eventTitle));
            if ($normalizedEventTitle === '') {
                $normalizedEventTitle = null;
            }
            $normalizedEventDate = $eventDate === null || trim($eventDate) === '' ? null : trim($eventDate);
            $normalizedEventTime = $eventTime === null || trim($eventTime) === '' ? null : trim($eventTime);
            if ($blockType === ContentBlockType::Event) {
                if ($normalizedEventTitle === null) {
                    throw new BadRequestHttpException('Eine Veranstaltung benötigt eine Überschrift.');
                }
                if ($normalizedEventDate === null || !$this->isValidDate($normalizedEventDate)) {
                    throw new BadRequestHttpException('Das Veranstaltungsdatum muss ein gültiges Datum sein.');
                }
                if ($normalizedEventTime === null || preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $normalizedEventTime) !== 1) {
                    throw new BadRequestHttpException('Die Veranstaltungszeit muss im Format HH:MM angegeben werden.');
                }
            }

            $mapped[] = new ContentBlock(
                type: $blockType,
                content: $content,
                mediaUrl: $mediaUrl === null || trim($mediaUrl) === '' ? null : trim($mediaUrl),
                mediaAlt: $mediaAlt === null || trim($mediaAlt) === '' ? null : trim($mediaAlt),
                mediaSource: $mediaSource === null || trim(strip_tags($mediaSource)) === '' ? null : trim(strip_tags($mediaSource)),
                linkUrl: $linkUrl === null || trim($linkUrl) === '' ? null : trim($linkUrl),
                linkLabel: $linkLabel === null || trim($linkLabel) === '' ? null : trim($linkLabel),
                layout: $layout === null || trim($layout) === '' ? null : trim($layout),
                imageWidthPercent: $imageWidthPercent,
                verticalAlignment: $verticalAlignment === null || trim($verticalAlignment) === '' ? null : trim($verticalAlignment),
                textAlignment: $textAlignment === null || trim($textAlignment) === '' ? null : trim($textAlignment),
                imageFit: $imageFit === null || trim($imageFit) === '' ? null : trim($imageFit),
                embeddedPageId: $embeddedPageId === null || trim($embeddedPageId) === '' ? null : trim($embeddedPageId),
                eventTitle: $normalizedEventTitle,
                eventDate: $normalizedEventDate,
                eventTime: $normalizedEventTime,
                eventIdentifier: $eventIdentifier === null || trim($eventIdentifier) === '' ? null : trim($eventIdentifier),
                eventHelpEnabled: $eventHelpEnabled,
                eventHelpButtonLabel: $eventHelpButtonLabel === null || trim($eventHelpButtonLabel) === '' ? null : trim($eventHelpButtonLabel),
                eventActivities: $mappedActivities,
                extensionKey: $extensionKey === null || trim($extensionKey) === '' ? null : trim($extensionKey),
            );
        }

        return $mapped;
    }

    private function isValidDate(string $date): bool
    {
        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);

        return $parsed !== false && $parsed->format('Y-m-d') === $date;
    }

    /**
     * @param InputBag<string|int|float|bool|null> $data
     */
    private function requiredString(InputBag $data, string $key): string
    {
        $value = trim($data->getString($key));
        if ($value === '') {
            throw new BadRequestHttpException(sprintf('Das Feld "%s" ist erforderlich.', $key));
        }

        return $value;
    }

    /**
     * @param InputBag<string|int|float|bool|null> $data
     */
    private function nullableString(InputBag $data, string $key): ?string
    {
        $value = trim($data->getString($key));

        return $value === '' ? null : $value;
    }
}
