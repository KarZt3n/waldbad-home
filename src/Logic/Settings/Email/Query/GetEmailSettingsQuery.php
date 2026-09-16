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
        return new EmailSettingsResponse(notificationRecipients: $this->manager->get()->notificationRecipients);
    }
}
