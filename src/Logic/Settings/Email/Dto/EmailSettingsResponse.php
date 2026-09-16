<?php

namespace App\Logic\Settings\Email\Dto;

readonly class EmailSettingsResponse
{
    /**
     * @param array<string, list<string>> $notificationRecipients Schlüssel = `NotificationEvent::$value`
     */
    public function __construct(
        public array $notificationRecipients,
    ) {
    }
}
