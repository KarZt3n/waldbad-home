<?php

namespace App\Logic\Membership\Member\EmailConsent\Model;

use App\Logic\Common\Exception\BusinessRuleViolationException;

/**
 * Ein per E-Mail verschickter, zeitlich begrenzter Bestätigungslink für die Doppel-Opt-in-Einwilligung
 * zum Erhalt von Vereinsinformationen per E-Mail (siehe `SendMemberEmailConsentRequestUseCase`,
 * `ConfirmMemberEmailConsentUseCase`) — nach Vorbild von
 * `App\Logic\IdentityAccess\LoginToken\Model\LoginToken`: einmalig verwendbar (`$confirmedAt`), ohne
 * zweiten Faktor.
 *
 * `$email` hält die Adresse zum Versandzeitpunkt fest (statt sie über `$memberId` neu nachzuschlagen)
 * — ändert sich die E-Mail-Adresse des Mitglieds zwischen Versand und Klick, bleibt der Link an die
 * ursprünglich angeschriebene Adresse gebunden.
 *
 * Der eigentliche Token wird nie im Klartext gespeichert, nur sein SHA-256-Hash (`$tokenHash`) — der
 * Token selbst ist bereits hochentropisch (siehe `SecureTokenGeneratorInterface`), ein
 * gesalzener/iterativer Hash ist für das Nachschlagen per exaktem Hash-Vergleich nicht nötig.
 */
readonly class MemberEmailConsentToken
{
    public function __construct(
        public string $id,
        public string $memberId,
        public string $email,
        public string $tokenHash,
        public \DateTimeImmutable $expiresAt,
        public ?\DateTimeImmutable $confirmedAt = null,
    ) {
        if (trim($this->memberId) === '') {
            throw new BusinessRuleViolationException('Die Mitglieds-ID ist erforderlich.');
        }
        if (trim($this->email) === '' || filter_var($this->email, FILTER_VALIDATE_EMAIL) === false) {
            throw new BusinessRuleViolationException('Die E-Mail-Adresse ist ungültig.');
        }
        if (trim($this->tokenHash) === '') {
            throw new BusinessRuleViolationException('Der Token-Hash ist erforderlich.');
        }
    }

    public function isExpired(\DateTimeImmutable $at): bool
    {
        return $at >= $this->expiresAt;
    }

    public function isConfirmed(): bool
    {
        return $this->confirmedAt !== null;
    }
}
