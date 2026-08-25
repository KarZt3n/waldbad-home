<?php

namespace App\Logic\Content\Page\Model;

use App\Logic\Common\Exception\BusinessRuleViolationException;

readonly class ContentCollectionItem
{
    public function __construct(
        public string $title,
        public string $content,
        public ?string $mediaUrl = null,
        public ?string $mediaAlt = null,
        public ?string $mediaSource = null,
    ) {
        if (trim($this->title) === '') {
            throw new BusinessRuleViolationException('Jeder Collection-Eintrag benötigt eine Überschrift.');
        }
        if (mb_strlen(trim($this->title)) > 160) {
            throw new BusinessRuleViolationException('Die Überschrift eines Collection-Eintrags darf höchstens 160 Zeichen lang sein.');
        }
        if ($this->mediaSource !== null && mb_strlen(trim($this->mediaSource)) > 300) {
            throw new BusinessRuleViolationException('Die Bildquelle darf höchstens 300 Zeichen lang sein.');
        }
    }
}
