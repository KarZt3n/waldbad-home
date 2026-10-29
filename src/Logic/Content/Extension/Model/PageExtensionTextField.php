<?php

namespace App\Logic\Content\Extension\Model;

readonly class PageExtensionTextField
{
    public const int MAX_LENGTH = 2000;

    public function __construct(
        public string $key,
        public string $label,
        public string $defaultText,
        public bool $multiline = false,
        /**
         * Wortlaut einer Bestätigung, der eine Person zustimmt. Solche Texte bilden die
         * Zustimmungsversion (siehe `PageExtensionTexts::consentVersion()`), damit nachvollziehbar
         * bleibt, welchem Wortlaut zugestimmt wurde.
         */
        public bool $consent = false,
    ) {
    }
}
