<?php

namespace App\Logic\IdentityAccess\LoginToken\UseCase;

use App\Logic\Common\ClockInterface;
use App\Logic\Common\IdentifierGeneratorInterface;
use App\Logic\Common\SecureTokenGeneratorInterface;
use App\Logic\IdentityAccess\LoginToken\LoginLinkBuilderInterface;
use App\Logic\IdentityAccess\LoginToken\Manager\LoginTokenManagerInterface;
use App\Logic\IdentityAccess\LoginToken\Model\LoginToken;
use App\Logic\IdentityAccess\User\Manager\UserManagerInterface;
use App\Logic\Settings\Email\Model\AssociationName;
use App\Logic\Settings\Email\Service\NotificationMailer;
use App\Logic\Settings\MailTemplate\Model\MailTemplateKey;
use Psr\Log\LoggerInterface;

/**
 * Fordert einen Anmeldelink für das Redaktions-Backend an (siehe `LoginToken`). Bewusst „best
 * effort" und ohne erkennbaren Unterschied im Verhalten, ob die E-Mail-Adresse zu einem aktiven
 * Benutzer gehört oder nicht (siehe `App\UI\IdentityAccess\Http\AuthenticationController`) — nach
 * demselben Muster wie `RequestMemberAccessUseCase` für „Meine Mitgliedschaft".
 */
readonly class RequestLoginUseCase
{
    private const int VALIDITY_MINUTES = 30;

    public function __construct(
        private UserManagerInterface $users,
        private LoginTokenManagerInterface $tokens,
        private SecureTokenGeneratorInterface $tokenGenerator,
        private IdentifierGeneratorInterface $identifierGenerator,
        private ClockInterface $clock,
        private NotificationMailer $notificationMailer,
        private LoginLinkBuilderInterface $linkBuilder,
        private LoggerInterface $logger,
    ) {
    }

    public function execute(string $email): void
    {
        $email = mb_strtolower(trim($email));
        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return;
        }

        try {
            $user = $this->users->findByEmail($email);
            if ($user === null || !$user->active) {
                return;
            }

            $rawToken = $this->tokenGenerator->generate();
            $expiresAt = $this->clock->now()->modify('+'.self::VALIDITY_MINUTES.' minutes');
            $this->tokens->save(new LoginToken(
                id: $this->identifierGenerator->generate(),
                email: $email,
                tokenHash: hash('sha256', $rawToken),
                expiresAt: $expiresAt,
            ));

            $this->notificationMailer->sendTo($email, MailTemplateKey::AdminLoginMagicLink, [
                'link' => $this->linkBuilder->build($rawToken),
                'gueltig_minuten' => (string) self::VALIDITY_MINUTES,
                'vereinsname' => AssociationName::CURRENT,
            ]);
        } catch (\Throwable $exception) {
            $this->logger->error('Anmeldelink für die Redaktion konnte nicht erstellt/versendet werden: {message}', [
                'message' => $exception->getMessage(),
            ]);
        }
    }
}
