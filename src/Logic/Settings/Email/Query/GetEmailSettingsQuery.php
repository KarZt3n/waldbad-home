<?php

namespace App\Logic\Settings\Email\Query;

use App\Logic\Settings\Email\Dto\EmailSettingsResponse;
use App\Logic\Settings\Email\Manager\EmailSettingsManagerInterface;

readonly class GetEmailSettingsQuery
{
    public function __construct(private EmailSettingsManagerInterface $manager)
    {
    }

    public function execute(): EmailSettingsResponse
    {
        $settings = $this->manager->get();

        return new EmailSettingsResponse(
            provider: $settings->provider?->value,
            host: $settings->host,
            port: $settings->port,
            username: $settings->username,
            passwordIsSet: $settings->password !== null,
            fromAddress: $settings->fromAddress,
            fromName: $settings->fromName,
            configured: $settings->isConfigured(),
            notificationRecipients: $settings->notificationRecipients,
        );
    }
}
