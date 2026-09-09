<?php

namespace App\Logic\Settings\Email\Model;

/**
 * Ereignisse, zu denen eine Benachrichtigungs-E-Mail an frei konfigurierbare Empfänger verschickt
 * wird (siehe `EmailSettings::$notificationRecipients`, `NotificationMailer`). Neue Ereignisse
 * werden hier ergänzt — die Oberfläche baut ihre Liste automatisch aus `label()`, es ist also keine
 * weitere Frontend-Änderung nötig, nur der eigentliche `NotificationMailer::notify()`-Aufruf an der
 * jeweiligen Stelle (siehe `SubmitMembershipApplicationUseCase` für das erste Beispiel).
 */
enum NotificationEvent: string
{
    case MembershipApplicationSubmitted = 'membership_application_submitted';

    public function label(): string
    {
        return match ($this) {
            self::MembershipApplicationSubmitted => 'Neuer Mitgliedsantrag eingegangen',
        };
    }
}
