<?php

namespace App\Logic\Membership;

/** Absolute Links in die Mitgliederverwaltung der Redaktion, für die Buttons in Benachrichtigungsmails. */
interface MembershipAdminLinkBuilderInterface
{
    public function applications(): string;

    public function messages(): string;

    public function cancellations(): string;
}
