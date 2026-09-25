<?php

namespace App\Logic\Media\PhotoAlbum\Model;

use App\Logic\Common\Exception\BusinessRuleViolationException;

/**
 * Aktionsbutton eines Foto-Eintrags (z. B. „Öffnen“ für das Album, „Ergebnisse“ für eine
 * Ergebnisliste) — verweist entweder auf eine URL oder auf eine CMS-Seite. Gleiche Regeln wie die
 * Aktionsbuttons einer Veranstaltung (`EventScheduleCallToAction`).
 */
readonly class PhotoAlbumAction
{
    public string $label;
    public ?string $url;
    public ?string $pageId;

    public function __construct(
        public string $id,
        public int $position,
        string $label,
        ?string $url,
        ?string $pageId,
    ) {
        $this->label = trim($label);
        $this->url = self::normalizeUrl($url);
        $this->pageId = $pageId === null || trim($pageId) === '' ? null : trim($pageId);

        if ($this->label === '') {
            throw new BusinessRuleViolationException('Ein Aktionsbutton benötigt eine Beschriftung.');
        }
        if (mb_strlen($this->label) > 80) {
            throw new BusinessRuleViolationException('Die Beschriftung eines Aktionsbuttons darf höchstens 80 Zeichen lang sein.');
        }
        if (($this->url !== null) === ($this->pageId !== null)) {
            throw new BusinessRuleViolationException('Ein Aktionsbutton muss entweder auf eine URL oder auf eine Seite verweisen.');
        }
        if ($this->url !== null) {
            if (mb_strlen($this->url) > 2048) {
                throw new BusinessRuleViolationException('Die URL eines Aktionsbuttons ist zu lang.');
            }
            if (preg_match('~^(?:https?://|mailto:|tel:|/|#)~i', $this->url) !== 1) {
                throw new BusinessRuleViolationException('Die URL eines Aktionsbuttons verwendet kein erlaubtes Ziel.');
            }
            if (preg_match('~^https?://~i', $this->url) === 1 && filter_var($this->url, FILTER_VALIDATE_URL) === false) {
                throw new BusinessRuleViolationException('Die URL eines Aktionsbuttons ist ungültig.');
            }
        }
    }

    private static function normalizeUrl(?string $url): ?string
    {
        if ($url === null || trim($url) === '') {
            return null;
        }
        $normalized = trim($url);
        if (str_starts_with($normalized, '//')) {
            return 'https:'.$normalized;
        }
        if (preg_match('~^(?:https?://|mailto:|tel:|/|#)~i', $normalized) === 1
            || preg_match('~^[a-z][a-z0-9+.-]*:(?!\d)~i', $normalized) === 1) {
            return $normalized;
        }

        return 'https://'.$normalized;
    }
}
