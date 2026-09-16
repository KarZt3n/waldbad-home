<?php

namespace App\Logic\IdentityAccess\LoginToken\Model;

use App\Logic\Common\Exception\BusinessRuleViolationException;

/**
 * Ein per E-Mail verschickter, zeitlich begrenzter Anmeldelink für das Redaktions-Backend (siehe
 * `RequestLoginUseCase`, `RedeemLoginTokenUseCase`) — nach Vorbild von
 * `App\Logic\Membership\MemberAccess\Model\MemberAccessToken`.
 *
 * Anders als dort: einmalig verwendbar (`$consumedAt`), weil das Einlösen hier eine volle
 * Redaktions-Sitzung eröffnet statt nur wiederholte, rein lesende Abrufe zu erlauben. Und ohne
 * zweiten Faktor (kein separates Passwort): eine Admin-Benutzer-E-Mail-Adresse identifiziert genau
 * eine Person, nicht — wie bei „Meine Mitgliedschaft" — potenziell einen ganzen Haushalt.
 *
 * Der eigentliche Token wird nie im Klartext gespeichert, nur sein SHA-256-Hash (`$tokenHash`) —
 * der Token selbst ist bereits hochentropisch (siehe `SecureTokenGeneratorInterface`), ein
 * gesalzener/iterativer Hash ist für das Nachschlagen per exaktem Hash-Vergleich nicht nötig.
 */
readonly class LoginToken
{
    public function __construct(
        public string $id,
        public string $email,
        public string $tokenHash,
        public \DateTimeImmutable $expiresAt,
        public ?\DateTimeImmutable $consumedAt = null,
    ) {
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

    public function isConsumed(): bool
    {
        return $this->consumedAt !== null;
    }
}
