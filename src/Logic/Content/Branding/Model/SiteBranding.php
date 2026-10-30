<?php

namespace App\Logic\Content\Branding\Model;

use App\Logic\Common\Exception\BusinessRuleViolationException;
use App\Logic\Common\SiteLogo;

/**
 * Einzeiliger Einstellungs-Datensatz (Singleton) für das Logo in der Kopfzeile der Website. Ohne
 * eigenes Logo wird das mitgelieferte Standardlogo (`SiteLogo::DEFAULT_URL`) angezeigt.
 */
readonly class SiteBranding
{
    public const int MAX_URL_LENGTH = 500;

    public ?string $customLogoUrl;

    public function __construct(?string $customLogoUrl)
    {
        $customLogoUrl = $customLogoUrl === null ? null : trim($customLogoUrl);
        if ($customLogoUrl === '') {
            $customLogoUrl = null;
        }
        if ($customLogoUrl !== null) {
            if (mb_strlen($customLogoUrl) > self::MAX_URL_LENGTH) {
                throw new BusinessRuleViolationException(sprintf('Die Logo-Adresse darf höchstens %d Zeichen lang sein.', self::MAX_URL_LENGTH));
            }
            // Nur Dateien der eigenen Website, damit das Logo nicht von fremden Servern nachgeladen wird.
            if (!str_starts_with($customLogoUrl, '/') || str_starts_with($customLogoUrl, '//') || preg_match('/[\s"\'<>\\\\]/', $customLogoUrl) === 1) {
                throw new BusinessRuleViolationException('Das Logo muss eine Datei dieser Website sein, z. B. aus der Mediathek (/uploads/…).');
            }
        }
        $this->customLogoUrl = $customLogoUrl;
    }

    public function logoUrl(): string
    {
        return $this->customLogoUrl ?? SiteLogo::DEFAULT_URL;
    }
}
