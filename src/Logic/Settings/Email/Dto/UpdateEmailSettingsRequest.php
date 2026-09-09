<?php

namespace App\Logic\Settings\Email\Dto;

use App\Logic\Settings\Email\Model\EmailProviderPreset;

readonly class UpdateEmailSettingsRequest
{
    /**
     * $password null lässt ein zuvor gespeichertes Passwort unverändert (die Oberfläche zeigt das
     * bestehende Passwort nie an und sendet das Feld deshalb nur bei einer tatsächlichen Änderung).
     */
    public function __construct(
        public ?EmailProviderPreset $provider,
        public ?string $host,
        public ?int $port,
        public ?string $username,
        public ?string $password,
        public ?string $fromAddress,
        public ?string $fromName,
    ) {
    }
}
