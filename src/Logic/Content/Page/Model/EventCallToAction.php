<?php

namespace App\Logic\Content\Page\Model;

use App\Logic\Common\Exception\BusinessRuleViolationException;

readonly class EventCallToAction
{
    public string $label;
    public ?string $url;
    public ?string $pageId;

    public function __construct(
        string $label,
        ?string $url,
        ?string $pageId,
    ) {
        $this->label = trim($label);
        $this->url = self::normalizeUrl($url);
        $this->pageId = $pageId === null || trim($pageId) === '' ? null : trim($pageId);

        if (trim($this->label) === '') {
            throw new BusinessRuleViolationException('Ein Aktionsbutton benötigt eine Beschriftung.');
        }

        if (mb_strlen(trim($this->label)) > 80) {
            throw new BusinessRuleViolationException('Die Beschriftung eines Aktionsbuttons darf höchstens 80 Zeichen lang sein.');
        }

        $hasUrl = $this->url !== null && trim($this->url) !== '';
        $hasPage = $this->pageId !== null && trim($this->pageId) !== '';
        if ($hasUrl === $hasPage) {
            throw new BusinessRuleViolationException('Ein Aktionsbutton muss entweder auf eine URL oder auf eine Seite verweisen.');
        }

        if ($this->url !== null && mb_strlen(trim($this->url)) > 2048) {
            throw new BusinessRuleViolationException('Die URL eines Aktionsbuttons ist zu lang.');
        }

        if ($hasUrl && preg_match('~^(?:https?://|mailto:|tel:|/|#)~i', trim((string) $this->url)) !== 1) {
            throw new BusinessRuleViolationException('Die URL eines Aktionsbuttons verwendet kein erlaubtes Ziel.');
        }

        if ($hasUrl && preg_match('~^https?://~i', (string) $this->url) === 1
            && filter_var($this->url, FILTER_VALIDATE_URL) === false) {
            throw new BusinessRuleViolationException('Die URL eines Aktionsbuttons ist ungültig.');
        }
    }

    private static function normalizeUrl(?string $url): ?string
    {
        if ($url === null || trim($url) === '') {
            return null;
        }

        $normalized = trim($url);
        if (preg_match('~^(?:https?://|mailto:|tel:|/|#)~i', $normalized) === 1) {
            return str_starts_with($normalized, '//') ? 'https:'.$normalized : $normalized;
        }

        if (preg_match('~^[a-z][a-z0-9+.-]*:(?!\d)~i', $normalized) === 1) {
            return $normalized;
        }

        return 'https://'.$normalized;
    }
}
