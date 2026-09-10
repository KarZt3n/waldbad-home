<?php

namespace App\Logic\Membership\MemberAccess\Model;

use App\Logic\Common\Exception\BusinessRuleViolationException;

/**
 * Ein per E-Mail verschickter, zeitlich begrenzter Zugang zu „Meine Mitgliedschaft“ (siehe
 * `RequestMemberAccessUseCase`, `ResolveMemberAccessSessionUseCase`) — die Kombination aus
 * E-Mail-Adresse und Geburtsdatum identifiziert bei der Anfrage genau ein Mitglied; der Token wird
 * an dessen Haushalt (`$primaryMemberNumber`, alle Mitglieder mit derselben Hauptnummer) gebunden,
 * nicht mehr breit an alle Mitgliedsdatensätze, die zufällig dieselbe E-Mail-Adresse verwenden.
 *
 * Der eigentliche Token wird nie im Klartext gespeichert, nur sein SHA-256-Hash (`$tokenHash`) —
 * anders als beim PIN-Schutz (`PinHasher`, langsames `password_hash`, da PINs wenig Entropie
 * haben) reicht hier ein schneller, deterministischer Hash: der Token selbst ist bereits
 * hochentropisch (siehe `SecureTokenGeneratorInterface`) und muss zum Nachschlagen per exaktem
 * Hash-Vergleich auffindbar sein, ein gesalzener/iterativer Hash wäre dafür ungeeignet.
 *
 * Zweiter Faktor neben dem Token in der Link-URL: ein separat in derselben Mail genanntes,
 * zufälliges Passwort (`$passwordHash`, siehe `RandomAccessPasswordGenerator`,
 * `MemberAccessPasswordHasher`) — muss bei jedem Aufruf zusätzlich zum Token mitgeschickt werden
 * (`ResolveMemberAccessSessionUseCase`). Anders als der Token selbst gesalzen/langsam gehasht, da es
 * mit 8 Zeichen deutlich weniger Entropie hat.
 *
 * Rein zeitlich begrenzt (`$expiresAt`, 30 Minuten ab Anfrage) statt einmalig verwendbar — ein
 * Seitenbesuch löst mehrere Aufrufe aus (Daten laden, ggf. eine Nachricht senden), die alle
 * innerhalb des Gültigkeitsfensters funktionieren sollen.
 */
readonly class MemberAccessToken
{
    public function __construct(
        public string $id,
        public string $email,
        public string $tokenHash,
        public \DateTimeImmutable $expiresAt,
        public string $primaryMemberNumber,
        public string $passwordHash,
    ) {
        if (trim($this->email) === '' || filter_var($this->email, FILTER_VALIDATE_EMAIL) === false) {
            throw new BusinessRuleViolationException('Die E-Mail-Adresse ist ungültig.');
        }
        if (trim($this->tokenHash) === '') {
            throw new BusinessRuleViolationException('Der Token-Hash ist erforderlich.');
        }
        if (trim($this->primaryMemberNumber) === '') {
            throw new BusinessRuleViolationException('Die Hauptnummer ist erforderlich.');
        }
        if (trim($this->passwordHash) === '') {
            throw new BusinessRuleViolationException('Der Passwort-Hash ist erforderlich.');
        }
    }

    public function isExpired(\DateTimeImmutable $at): bool
    {
        return $at >= $this->expiresAt;
    }
}
