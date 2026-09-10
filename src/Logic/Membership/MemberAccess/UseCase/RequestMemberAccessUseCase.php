<?php

namespace App\Logic\Membership\MemberAccess\UseCase;

use App\Logic\Common\AccessPasswordGeneratorInterface;
use App\Logic\Common\ClockInterface;
use App\Logic\Common\IdentifierGeneratorInterface;
use App\Logic\Common\SecureTokenGeneratorInterface;
use App\Logic\Membership\Member\Manager\MemberManagerInterface;
use App\Logic\Membership\Member\Model\Member;
use App\Logic\Membership\MemberAccess\MemberAccessLinkBuilderInterface;
use App\Logic\Membership\MemberAccess\Manager\MemberAccessTokenManagerInterface;
use App\Logic\Membership\MemberAccess\Model\MemberAccessToken;
use App\Logic\Membership\MemberAccess\Service\MemberAccessPasswordHasher;
use App\Logic\Settings\Email\Model\AssociationName;
use App\Logic\Settings\Email\Service\NotificationMailer;
use App\Logic\Settings\MailTemplate\Model\MailTemplateKey;
use Psr\Log\LoggerInterface;

/**
 * Fordert einen zeitlich begrenzten Zugang zu „Meine Mitgliedschaft" an (siehe `MemberAccessToken`).
 * Bewusst „best effort" und ohne erkennbaren Unterschied im Verhalten, ob die Kombination aus
 * E-Mail-Adresse und Geburtsdatum zu einem Mitglied gehört oder nicht: der öffentliche Endpunkt
 * (`PublicMemberAccessController`) gibt immer dieselbe Antwort zurück, damit sich über diesen Weg
 * nicht herausfinden lässt, welche Kombinationen als Mitglied existieren. Das Geburtsdatum ist
 * zusätzlich zur E-Mail-Adresse nötig, weil eine E-Mail-Adresse in der Regel einen ganzen Haushalt
 * identifiziert (siehe `ReleaseMembershipApplicationUseCase`) — erst das Geburtsdatum grenzt auf
 * eine einzelne Person ein, deren Haushalt (`Member::$primaryMemberNumber`) dann den Zugang erhält.
 *
 * Neben dem Link verschickt dieselbe Mail ein separates, zufälliges Passwort (siehe
 * `AccessPasswordGeneratorInterface`) — zweiter Faktor, der zusätzlich zum Token benötigt wird
 * (siehe `MemberAccessToken`).
 */
readonly class RequestMemberAccessUseCase
{
    private const int VALIDITY_MINUTES = 30;

    public function __construct(
        private MemberManagerInterface $members,
        private MemberAccessTokenManagerInterface $tokens,
        private SecureTokenGeneratorInterface $tokenGenerator,
        private AccessPasswordGeneratorInterface $passwordGenerator,
        private MemberAccessPasswordHasher $passwordHasher,
        private IdentifierGeneratorInterface $identifierGenerator,
        private ClockInterface $clock,
        private NotificationMailer $notificationMailer,
        private MemberAccessLinkBuilderInterface $linkBuilder,
        private LoggerInterface $logger,
    ) {
    }

    public function execute(string $email, string $birthDate): void
    {
        $email = mb_strtolower(trim($email));
        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return;
        }
        $parsedBirthDate = \DateTimeImmutable::createFromFormat('!Y-m-d', trim($birthDate));
        if ($parsedBirthDate === false) {
            return;
        }

        try {
            $match = $this->matchingMember($email, $parsedBirthDate);
            if ($match === null) {
                return;
            }

            $rawToken = $this->tokenGenerator->generate();
            $password = $this->passwordGenerator->generate();
            $expiresAt = $this->clock->now()->modify('+'.self::VALIDITY_MINUTES.' minutes');
            $this->tokens->save(new MemberAccessToken(
                id: $this->identifierGenerator->generate(),
                email: $email,
                tokenHash: hash('sha256', $rawToken),
                expiresAt: $expiresAt,
                primaryMemberNumber: $match->primaryMemberNumber,
                passwordHash: $this->passwordHasher->hash($password),
            ));

            $this->notificationMailer->sendTo($email, MailTemplateKey::MemberAccessMagicLink, [
                'link' => $this->linkBuilder->build($rawToken),
                'passwort' => $password,
                'gueltig_minuten' => (string) self::VALIDITY_MINUTES,
                'vereinsname' => AssociationName::CURRENT,
            ]);
        } catch (\Throwable $exception) {
            $this->logger->error('Zugangslink für "Meine Mitgliedschaft" konnte nicht erstellt/versendet werden: {message}', [
                'message' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * Von allen Mitgliedern mit dieser E-Mail-Adresse (in der Regel ein Haushalt) dasjenige, dessen
     * Geburtsdatum ebenfalls passt — grenzt so auf die anfragende Person ein. Gibt es mehrere
     * Treffer (z. B. Zwillinge mit gemeinsamer Adresse), reicht irgendeiner davon, da alle demselben
     * Haushalt angehören.
     */
    private function matchingMember(string $email, \DateTimeImmutable $birthDate): ?Member
    {
        foreach ($this->members->findByEmail($email) as $member) {
            if ($member->birthDate->format('Y-m-d') === $birthDate->format('Y-m-d')) {
                return $member;
            }
        }

        return null;
    }
}
