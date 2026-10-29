<?php

namespace App\Logic\Membership\Application\Model;

/**
 * Der aktuell gültige, redaktionell pflegbare Wortlaut der Bestätigungen im Mitgliedsantrag und
 * seine Version (ändert sich mit jedem geänderten Wortlaut, siehe `MembershipDeclarationProviderInterface`).
 */
readonly class MembershipDeclaration
{
    public function __construct(
        public string $version,
        public string $terms,
        public string $privacy,
        public string $sepa,
        public string $emailConsent,
    ) {
    }

    /**
     * @return list<string> der Wortlaut aller Bestätigungen, denen mit dem Antrag zugestimmt wurde
     */
    public function accepted(bool $emailConsent): array
    {
        return [$this->terms, $this->privacy, $this->sepa, ...($emailConsent ? [$this->emailConsent] : [])];
    }
}
