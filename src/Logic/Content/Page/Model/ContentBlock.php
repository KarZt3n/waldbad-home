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
        public ?string $extensionKey = null,
    ) {
        if (!in_array($this->type, [ContentBlockType::EmbeddedPage, ContentBlockType::Event, ContentBlockType::EventReference, ContentBlockType::Extension], true)
            && trim($this->content) === ''
            && $this->mediaUrl === null
            && $this->embeddedPageId === null) {
            throw new BusinessRuleViolationException('Ein Inhaltsblock benötigt Text oder ein Medium.');
        }

        if ($this->type === ContentBlockType::EmbeddedPage && $this->embeddedPageId === null) {
            throw new BusinessRuleViolationException('Eine eingebettete Seite muss ausgewählt werden.');
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

        if (in_array($this->type, [ContentBlockType::Image, ContentBlockType::ImageText], true)
            && $this->mediaUrl === null) {
            throw new BusinessRuleViolationException('Ein Bildblock benötigt eine Bild-URL.');
        }

        if ($this->type === ContentBlockType::ImageText && !in_array($this->layout, ['image_left', 'image_right'], true)) {
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

        if ($this->type === ContentBlockType::ImageText
            && ($this->imageWidthPercent !== null && ($this->imageWidthPercent < 20 || $this->imageWidthPercent > 80))) {
            throw new BusinessRuleViolationException('Die Bildbreite muss zwischen 20 und 80 Prozent liegen.');
        }

        if ($this->type === ContentBlockType::EventReference
            && ($this->imageWidthPercent !== null
                && ($this->imageWidthPercent < 20
                    || $this->imageWidthPercent > ($this->layout === 'image_top' ? 100 : 80)))) {
            throw new BusinessRuleViolationException('Die Bildbreite ist für die gewählte Anordnung ungültig.');
        }

        if (in_array($this->type, [ContentBlockType::ImageText, ContentBlockType::EventReference], true)
            && ($this->verticalAlignment !== null && !in_array($this->verticalAlignment, ['top', 'center', 'bottom'], true))) {
            throw new BusinessRuleViolationException('Die vertikale Textausrichtung ist ungültig.');
        }

        if (in_array($this->type, [ContentBlockType::ImageText, ContentBlockType::EventReference], true)
            && ($this->textAlignment !== null && !in_array($this->textAlignment, ['left', 'center', 'right'], true))) {
            throw new BusinessRuleViolationException('Die horizontale Textausrichtung ist ungültig.');
        }

        if (in_array($this->type, [ContentBlockType::ImageText, ContentBlockType::EventReference], true)
            && ($this->imageFit !== null && !in_array($this->imageFit, ['cover', 'contain'], true))) {
            throw new BusinessRuleViolationException('Die Bilddarstellung ist ungültig.');
        }

        if ($this->type === ContentBlockType::CallToAction && ($this->linkUrl === null || $this->linkLabel === null)) {
            throw new BusinessRuleViolationException('Ein Call-to-Action benötigt Link und Beschriftung.');
        }

        if ($this->type === ContentBlockType::Extension && $this->extensionKey !== 'membership_application') {
            throw new BusinessRuleViolationException('Die ausgewählte Seitenerweiterung ist ungültig.');
        }
    }
}
