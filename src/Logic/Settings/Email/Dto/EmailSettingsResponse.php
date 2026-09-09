<?php

namespace App\Logic\Settings\Email\Dto;

readonly class EmailSettingsResponse
{
    /**
     * @param array<string, list<string>> $notificationRecipients Schlüssel = `NotificationEvent::$value`
     */
    public function __construct(
        public ?string $provider,
        public ?string $host,
        public ?int $port,
        public ?string $username,
        public bool $passwordIsSet,
        public ?string $fromAddress,
        public ?string $fromName,
        public bool $configured,
        public array $notificationRecipients,
    ) {
    }
}
