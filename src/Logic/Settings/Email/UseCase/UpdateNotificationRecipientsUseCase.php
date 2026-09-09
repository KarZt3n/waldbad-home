<?php

namespace App\Logic\Settings\Email\UseCase;

use App\Logic\Common\Exception\BusinessRuleViolationException;
use App\Logic\Settings\Email\Manager\EmailSettingsManagerInterface;
use App\Logic\Settings\Email\Model\NotificationEvent;

readonly class UpdateNotificationRecipientsUseCase
{
    public function __construct(private EmailSettingsManagerInterface $manager)
    {
    }

    /**
     * @param list<string> $recipients
     */
    public function execute(NotificationEvent $event, array $recipients): void
    {
        $normalized = [];
        foreach ($recipients as $recipient) {
            $email = mb_strtolower(trim($recipient));
            if ($email === '') {
                continue;
            }
            if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                throw new BusinessRuleViolationException(sprintf('„%s“ ist keine gültige E-Mail-Adresse.', $recipient));
            }
            $normalized[$email] = true;
        }

        $settings = $this->manager->get();
        $this->manager->save($settings->withRecipientsFor($event, array_keys($normalized)));
    }
}
