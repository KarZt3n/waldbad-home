<?php

namespace App\Data\Settings\MailTemplate;

use App\Logic\Common\SiteLogo;
use App\Logic\Settings\MailTemplate\Model\EmailLogo;
use App\Logic\Settings\MailTemplate\Service\EmailLogoProviderInterface;

/**
 * Liest das Vereinslogo (`SiteLogo::DEFAULT_URL` unter `public/`) einmalig ein und hält es im
 * Speicher. Fehlt die Datei (z. B. in einer minimalen Testumgebung), wird das nicht als Fehler
 * behandelt — die Mail wird dann eben ohne Logo verschickt.
 */
final class FilesystemEmailLogoProvider implements EmailLogoProviderInterface
{
    private const array MIME_TYPES_BY_EXTENSION = [
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'svg' => 'image/svg+xml',
        'webp' => 'image/webp',
    ];

    private bool $resolved = false;
    private ?EmailLogo $logo = null;

    public function __construct(private readonly string $publicDirectory)
    {
    }

    public function getLogo(): ?EmailLogo
    {
        if ($this->resolved) {
            return $this->logo;
        }
        $this->resolved = true;

        $logoPath = $this->publicDirectory.SiteLogo::DEFAULT_URL;
        $contents = @file_get_contents($logoPath);
        if ($contents === false) {
            return $this->logo = null;
        }

        $extension = strtolower(pathinfo($logoPath, PATHINFO_EXTENSION));

        return $this->logo = new EmailLogo($contents, self::MIME_TYPES_BY_EXTENSION[$extension] ?? 'application/octet-stream');
    }
}
