<?php

namespace App\Logic\Settings\Email\UseCase;

use App\Logic\Common\Exception\BusinessRuleViolationException;
use App\Logic\Settings\Email\Dto\UpdateEmailSettingsRequest;
use App\Logic\Settings\Email\Manager\EmailSettingsManagerInterface;

/**
 * Speichert die SMTP-Verbindungsdaten (Anbieter-Preset nur als Merkzettel für die Oberfläche, siehe
 * `EmailProviderPreset`). Ändert nicht die Empfänger je Ereignis (siehe
 * `UpdateNotificationRecipientsUseCase`).
 */
readonly class UpdateEmailSettingsUseCase
{
    public function __construct(private EmailSettingsManagerInterface $manager)
    {
    }

    public function execute(UpdateEmailSettingsRequest $request): void
    {
        if ($request->fromAddress !== null && trim($request->fromAddress) !== ''
            && filter_var($request->fromAddress, FILTER_VALIDATE_EMAIL) === false
        ) {
            throw new BusinessRuleViolationException('Die Absender-E-Mail-Adresse ist ungültig.');
        }
        if ($request->port !== null && ($request->port < 1 || $request->port > 65535)) {
            throw new BusinessRuleViolationException('Der Port muss zwischen 1 und 65535 liegen.');
        }

        $settings = $this->manager->get();
        $this->manager->save($settings->withConnection(
            provider: $request->provider,
            host: $this->nullIfBlank($request->host),
            port: $request->port,
            username: $this->nullIfBlank($request->username),
            // null lässt ein bestehendes Passwort unverändert; nur ein tatsächlich übermittelter,
            // nicht-leerer Wert ersetzt es.
            password: $request->password !== null && trim($request->password) !== '' ? $request->password : $settings->password,
            fromAddress: $this->nullIfBlank($request->fromAddress),
            fromName: $this->nullIfBlank($request->fromName),
        ));
    }

    private function nullIfBlank(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
