<?php

namespace App\Logic\Content\Page\Model;

use App\Logic\Common\Exception\BusinessRuleViolationException;

readonly class ContentBlock
{
    public function __construct(
        public ContentBlockType $type,
        public string $content,
        public ?string $mediaUrl = null,
        public ?string $mediaAlt = null,
        public ?string $mediaSource = null,
        public ?string $linkUrl = null,
        public ?string $linkLabel = null,
        public ?string $layout = null,
        public ?int $imageWidthPercent = null,
        public ?string $verticalAlignment = null,
        public ?string $textAlignment = null,
        public ?string $imageFit = null,
        public ?string $embeddedPageId = null,
        public ?string $eventTitle = null,
        public ?string $eventDate = null,
        public ?string $eventTime = null,
        public ?string $eventIdentifier = null,
        public bool $eventHelpEnabled = false,
        public ?string $eventHelpButtonLabel = null,
        /** @var list<EventActivityAssignment> */
        public array $eventActivities = [],
        /** @var list<EventCallToAction> */
        public array $eventCallToActions = [],
        public ?string $extensionKey = null,
        public ?int $collectionColumns = null,
        /** @var list<ContentCollectionItem> */
        public array $collectionItems = [],
    ) {
        if (!in_array($this->type, [ContentBlockType::EmbeddedPage, ContentBlockType::PageTeaser, ContentBlockType::Event, ContentBlockType::EventReference, ContentBlockType::Extension], true)
            && trim($this->content) === ''
            && $this->mediaUrl === null
            && $this->embeddedPageId === null) {
            throw new BusinessRuleViolationException('Ein Inhaltsblock benötigt Text oder ein Medium.');
        }

        if ($this->type === ContentBlockType::EmbeddedPage && $this->embeddedPageId === null) {
            throw new BusinessRuleViolationException('Eine eingebettete Seite muss ausgewählt werden.');
        }

        if ($this->type === ContentBlockType::PageTeaser && $this->embeddedPageId === null) {
            throw new BusinessRuleViolationException('Ein Seitenteaser benötigt eine Zielseite.');
        }

        if ($this->type === ContentBlockType::EventReference
            && ($this->embeddedPageId === null || $this->eventIdentifier === null || trim($this->eventIdentifier) === '')) {
            throw new BusinessRuleViolationException('Eine eingebettete Veranstaltung muss ausgewählt werden.');
        }

        if ($this->type === ContentBlockType::Event
            && ($this->eventTitle === null || $this->eventDate === null || $this->eventTime === null)) {
            throw new BusinessRuleViolationException('Eine Veranstaltung benötigt Überschrift, Datum und Uhrzeit.');
        }

        if ($this->type === ContentBlockType::Event && $this->eventHelpEnabled
            && ($this->eventIdentifier === null || trim($this->eventIdentifier) === '')) {
            throw new BusinessRuleViolationException('Eine Veranstaltung mit Helferanmeldung benötigt eine stabile Kennung.');
        }

        if ($this->eventHelpButtonLabel !== null && mb_strlen(trim($this->eventHelpButtonLabel)) > 80) {
            throw new BusinessRuleViolationException('Die Beschriftung der Helferanmeldung ist zu lang.');
        }

        if ($this->type !== ContentBlockType::Event && $this->eventCallToActions !== []) {
            throw new BusinessRuleViolationException('Zusätzliche Aktionsbuttons sind nur bei Veranstaltungen erlaubt.');
        }

        $activityIds = array_map(static fn (EventActivityAssignment $assignment): string => $assignment->activityId, $this->eventActivities);
        if (count($activityIds) !== count(array_unique($activityIds))) {
            throw new BusinessRuleViolationException('Eine Aktivität darf einer Veranstaltung nur einmal zugeordnet werden.');
        }

        if ($this->mediaSource !== null && mb_strlen(trim($this->mediaSource)) > 300) {
            throw new BusinessRuleViolationException('Die Bildquelle darf höchstens 300 Zeichen lang sein.');
        }

        if (in_array($this->type, [ContentBlockType::Image, ContentBlockType::ImageText], true)
            && $this->mediaUrl === null) {
            throw new BusinessRuleViolationException('Ein Bildblock benötigt eine Bild-URL.');
        }

        if (in_array($this->type, [ContentBlockType::ImageText, ContentBlockType::PageTeaser], true)
            && !in_array($this->layout, ['image_left', 'image_right'], true)) {
            throw new BusinessRuleViolationException('Für Bild und Text muss die Bildposition gewählt werden.');
        }

        if ($this->type === ContentBlockType::EventReference
            && $this->layout !== null
            && !in_array($this->layout, ['image_left', 'image_right', 'image_top'], true)) {
            throw new BusinessRuleViolationException('Für die eingebettete Veranstaltung muss die Bildposition gewählt werden.');
        }

        if ($this->type === ContentBlockType::Image
            && ($this->layout !== null && !in_array($this->layout, ['left', 'center', 'right'], true))) {
            throw new BusinessRuleViolationException('Die Bildausrichtung ist ungültig.');
        }

        if ($this->type === ContentBlockType::Image
            && ($this->imageWidthPercent !== null && ($this->imageWidthPercent < 20 || $this->imageWidthPercent > 100))) {
            throw new BusinessRuleViolationException('Die Bildbreite muss zwischen 20 und 100 Prozent liegen.');
        }

        if (in_array($this->type, [ContentBlockType::ImageText, ContentBlockType::PageTeaser], true)
            && ($this->imageWidthPercent !== null && ($this->imageWidthPercent < 20 || $this->imageWidthPercent > 80))) {
            throw new BusinessRuleViolationException('Die Bildbreite muss zwischen 20 und 80 Prozent liegen.');
        }

        if ($this->type === ContentBlockType::EventReference
            && ($this->imageWidthPercent !== null
                && ($this->imageWidthPercent < 20
                    || $this->imageWidthPercent > ($this->layout === 'image_top' ? 100 : 80)))) {
            throw new BusinessRuleViolationException('Die Bildbreite ist für die gewählte Anordnung ungültig.');
        }

        if (in_array($this->type, [ContentBlockType::ImageText, ContentBlockType::PageTeaser, ContentBlockType::EventReference], true)
            && ($this->verticalAlignment !== null && !in_array($this->verticalAlignment, ['top', 'center', 'bottom'], true))) {
            throw new BusinessRuleViolationException('Die vertikale Textausrichtung ist ungültig.');
        }

        if (in_array($this->type, [ContentBlockType::ImageText, ContentBlockType::PageTeaser, ContentBlockType::EventReference], true)
            && ($this->textAlignment !== null && !in_array($this->textAlignment, ['left', 'center', 'right'], true))) {
            throw new BusinessRuleViolationException('Die horizontale Textausrichtung ist ungültig.');
        }

        if (in_array($this->type, [ContentBlockType::ImageText, ContentBlockType::PageTeaser, ContentBlockType::EventReference], true)
            && ($this->imageFit !== null && !in_array($this->imageFit, ['cover', 'contain'], true))) {
            throw new BusinessRuleViolationException('Die Bilddarstellung ist ungültig.');
        }

        if ($this->type === ContentBlockType::CallToAction && ($this->linkUrl === null || $this->linkLabel === null)) {
            throw new BusinessRuleViolationException('Ein Call-to-Action benötigt Link und Beschriftung.');
        }

        if ($this->type === ContentBlockType::Extension && !in_array($this->extensionKey, [
            'membership_application',
            'events_current_year',
            'events_from_current_year',
            'work_assignments_current_year',
            'next_event',
            'next_work_assignment',
            'next_event_or_work_assignment',
            'sauna',
            'photo_albums',
        ], true)) {
            throw new BusinessRuleViolationException('Die ausgewählte Seitenerweiterung ist ungültig.');
        }

        if ($this->type === ContentBlockType::FeatureCollection
            && ($this->collectionColumns === null || $this->collectionColumns < 1 || $this->collectionColumns > 4)) {
            throw new BusinessRuleViolationException('Die Collection muss zwischen einer und vier Spalten besitzen.');
        }

        if ($this->type === ContentBlockType::FeatureCollection && $this->collectionItems === []) {
            throw new BusinessRuleViolationException('Die Collection benötigt mindestens einen Eintrag.');
        }
    }

    /**
     * @return list<string>
     */
    public function mediaUrls(): array
    {
        $urls = $this->mediaUrl === null ? [] : [$this->mediaUrl];
        foreach ($this->collectionItems as $item) {
            if ($item->mediaUrl !== null) {
                $urls[] = $item->mediaUrl;
            }
        }

        return array_values(array_unique($urls));
    }

    /**
     * Liefert `null`, wenn der Block ohne das entfernte Bild keinen Inhalt mehr hätte.
     */
    public function withMediaReplaced(string $url, ?string $replacementUrl): ?self
    {
        if (!in_array($url, $this->mediaUrls(), true)) {
            return $this;
        }

        $collectionItems = array_map(
            static fn (ContentCollectionItem $item): ContentCollectionItem => $item->withMediaReplaced($url, $replacementUrl),
            $this->collectionItems,
        );
        if ($this->mediaUrl !== $url) {
            return $this->withMedia($this->mediaUrl, $this->mediaAlt, $this->mediaSource, $collectionItems);
        }
        if ($replacementUrl !== null) {
            return $this->withMedia($replacementUrl, $this->mediaAlt, $this->mediaSource, $collectionItems);
        }

        if ($this->type === ContentBlockType::Image) {
            return null;
        }
        if ($this->type === ContentBlockType::ImageText) {
            return trim($this->content) === '' ? null : new self(type: ContentBlockType::RichText, content: $this->content);
        }
        if (!in_array($this->type, [ContentBlockType::EmbeddedPage, ContentBlockType::PageTeaser, ContentBlockType::Event, ContentBlockType::EventReference, ContentBlockType::Extension], true)
            && trim($this->content) === ''
            && $this->embeddedPageId === null) {
            return null;
        }

        return $this->withMedia(null, null, null, $collectionItems);
    }

    /**
     * @param list<ContentCollectionItem> $collectionItems
     */
    private function withMedia(?string $mediaUrl, ?string $mediaAlt, ?string $mediaSource, array $collectionItems): self
    {
        return new self(
            type: $this->type,
            content: $this->content,
            mediaUrl: $mediaUrl,
            mediaAlt: $mediaAlt,
            mediaSource: $mediaSource,
            linkUrl: $this->linkUrl,
            linkLabel: $this->linkLabel,
            layout: $this->layout,
            imageWidthPercent: $this->imageWidthPercent,
            verticalAlignment: $this->verticalAlignment,
            textAlignment: $this->textAlignment,
            imageFit: $this->imageFit,
            embeddedPageId: $this->embeddedPageId,
            eventTitle: $this->eventTitle,
            eventDate: $this->eventDate,
            eventTime: $this->eventTime,
            eventIdentifier: $this->eventIdentifier,
            eventHelpEnabled: $this->eventHelpEnabled,
            eventHelpButtonLabel: $this->eventHelpButtonLabel,
            eventActivities: $this->eventActivities,
            eventCallToActions: $this->eventCallToActions,
            extensionKey: $this->extensionKey,
            collectionColumns: $this->collectionColumns,
            collectionItems: $collectionItems,
        );
    }
}
