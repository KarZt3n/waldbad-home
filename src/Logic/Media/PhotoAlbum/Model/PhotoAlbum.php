<?php

namespace App\Logic\Media\PhotoAlbum\Model;

use App\Logic\Common\Exception\BusinessRuleViolationException;

/**
 * Eintrag im Fotoarchiv: Titel (wird so angezeigt, wie er gepflegt ist), Datum zur Einordnung ins
 * Jahr und zur Sortierung sowie Aktionsbuttons zum Album, zu Ergebnissen o. Ä. Ein Eintrag ohne
 * Aktionsbutton ist erlaubt (reiner Hinweis, z. B. „leider keine Fotos vorhanden“).
 */
readonly class PhotoAlbum
{
    public const int MAX_ACTIONS = 10;

    /**
     * @param list<PhotoAlbumAction> $actions
     */
    public function __construct(
        public string $id,
        public string $title,
        public \DateTimeImmutable $date,
        public bool $visible,
        public array $actions,
        public \DateTimeImmutable $createdAt,
        public \DateTimeImmutable $updatedAt,
    ) {
        if (trim($this->title) === '' || mb_strlen(trim($this->title)) > 250) {
            throw new BusinessRuleViolationException('Ein Foto-Eintrag benötigt einen Titel mit höchstens 250 Zeichen.');
        }
        if (count($this->actions) > self::MAX_ACTIONS) {
            throw new BusinessRuleViolationException('Ein Foto-Eintrag kann höchstens zehn Aktionsbuttons besitzen.');
        }
    }

    /**
     * @param list<PhotoAlbumAction> $actions
     */
    public function revise(string $title, \DateTimeImmutable $date, bool $visible, array $actions, \DateTimeImmutable $updatedAt): self
    {
        return new self($this->id, $title, $date, $visible, $actions, $this->createdAt, $updatedAt);
    }
}
