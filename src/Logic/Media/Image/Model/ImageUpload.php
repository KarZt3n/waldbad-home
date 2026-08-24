<?php

namespace App\Logic\Media\Image\Model;

use App\Logic\Common\Exception\BusinessRuleViolationException;

readonly class ImageUpload
{
    public const int MAX_SIZE = 10_000_000;

    public function __construct(
        public string $temporaryPath,
        public string $originalName,
        public int $size,
        public ?string $source = null,
    ) {
        if ($this->temporaryPath === '' || $this->originalName === '') {
            throw new BusinessRuleViolationException('Die Bilddatei ist ungültig.');
        }

        if ($this->size < 1 || $this->size > self::MAX_SIZE) {
            throw new BusinessRuleViolationException('Das Bild darf maximal 10 MB groß sein.');
        }

        if ($this->source !== null && mb_strlen(trim($this->source)) > 300) {
            throw new BusinessRuleViolationException('Die Bildquelle darf höchstens 300 Zeichen lang sein.');
        }
    }
}
