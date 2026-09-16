<?php

namespace App\Tests\Unit\Logic\IdentityAccess\Session\UseCase;

use App\Logic\Common\ClockInterface;
use App\Logic\IdentityAccess\Session\Dto\IssuedSession;
use App\Logic\IdentityAccess\Session\Exception\InvalidSessionException;
use App\Logic\IdentityAccess\Session\Manager\RefreshTokenManagerInterface;
use App\Logic\IdentityAccess\Session\Model\RefreshToken;
use App\Logic\IdentityAccess\Session\Service\SessionTokenIssuer;
use App\Logic\IdentityAccess\Session\UseCase\RefreshSessionUseCase;
use App\Logic\IdentityAccess\User\Manager\UserManagerInterface;
use App\Logic\IdentityAccess\User\Model\CmsModule;
use App\Logic\IdentityAccess\User\Model\ModuleAccess;
use App\Logic\IdentityAccess\User\Model\ModuleRole;
use App\Logic\IdentityAccess\User\Model\User;
use PHPUnit\Framework\TestCase;

final class RefreshSessionUseCaseTest extends TestCase
{
    private const string NOW = '2026-06-01T10:00:00+02:00';

    public function testThrowsForAnUnknownToken(): void
    {
        $tokens = $this->createStub(RefreshTokenManagerInterface::class);
        $tokens->method('findByHash')->willReturn(null);

        $this->expectException(InvalidSessionException::class);

        $this->useCase($tokens)->execute('raw-refresh-token');
    }

    /**
     * Ein bereits rotiertes (gesperrtes) Token wird erneut vorgelegt — Hinweis auf einen
     * gestohlenen Token: statt nur diesen einen Token abzulehnen, werden vorsorglich alle
     * Refresh-Tokens des Benutzers gesperrt.
     */
    public function testRevokesAllTokensForTheUserWhenAnAlreadyRevokedTokenIsPresented(): void
    {
        $now = new \DateTimeImmutable(self::NOW);
        $tokens = $this->createMock(RefreshTokenManagerInterface::class);
        $tokens->method('findByHash')->willReturn($this->token(revokedAt: new \DateTimeImmutable('2026-06-01T09:55:00+02:00')));
        $tokens->expects(self::once())->method('revokeAllForUser')->with('user-1', $now);
        $tokens->expects(self::never())->method('revoke');

        $this->expectException(InvalidSessionException::class);

        $this->useCase($tokens, now: $now)->execute('raw-refresh-token');
    }

    /**
     * Wurde das Token erst vor Kurzem rotiert (hier: 5 Sekunden), wird die Wiedervorlage als
     * harmloser Wettlauf behandelt (z. B. zwei Redaktions-Tabs, deren Refresh-Timer kollidieren) —
     * statt die gesamte Sitzung zu beenden, wird einfach erneut ausgestellt.
     */
    public function testReissuesForAnAlreadyRevokedTokenWithinTheGracePeriod(): void
    {
        $now = new \DateTimeImmutable(self::NOW);
        $token = $this->token(revokedAt: new \DateTimeImmutable('2026-06-01T09:59:55+02:00'));

        $tokens = $this->createMock(RefreshTokenManagerInterface::class);
        $tokens->method('findByHash')->willReturn($token);
        $tokens->expects(self::never())->method('revokeAllForUser');
        $tokens->expects(self::never())->method('revoke');

        $users = $this->createStub(UserManagerInterface::class);
        $users->method('get')->willReturn($this->user());

        $issuedTokens = new IssuedSession('raw-access', $now->modify('+15 minutes'), 'raw-refresh-2', $now->modify('+15 minutes'));
        $issuer = $this->createMock(SessionTokenIssuer::class);
        $issuer->expects(self::once())->method('rotate')->with($token)->willReturn($issuedTokens);

        $session = $this->useCase($tokens, $users, $issuer, $now)->execute('raw-refresh-token');

        self::assertSame($issuedTokens, $session->tokens);
    }

    public function testRevokesAndThrowsForAnExpiredToken(): void
    {
        $now = new \DateTimeImmutable(self::NOW);
        $tokens = $this->createMock(RefreshTokenManagerInterface::class);
        $tokens->method('findByHash')->willReturn($this->token(expiresAt: new \DateTimeImmutable('2026-06-01T09:59:59+02:00')));
        $tokens->expects(self::once())->method('revoke')->with('refresh-1', $now);
        $tokens->expects(self::never())->method('revokeAllForUser');

        $this->expectException(InvalidSessionException::class);

        $this->useCase($tokens, now: $now)->execute('raw-refresh-token');
    }

    public function testRevokesAndThrowsWhenTheUserIsSuspended(): void
    {
        $now = new \DateTimeImmutable(self::NOW);
        $tokens = $this->createMock(RefreshTokenManagerInterface::class);
        $tokens->method('findByHash')->willReturn($this->token());
        $tokens->expects(self::once())->method('revoke')->with('refresh-1', $now);

        $users = $this->createStub(UserManagerInterface::class);
        $users->method('get')->willReturn($this->user(active: false));

        $this->expectException(InvalidSessionException::class);

        $this->useCase($tokens, $users, now: $now)->execute('raw-refresh-token');
    }

    /**
     * Kernstück: ein gültiges, noch nicht rotiertes Token wird gesperrt (nicht gelöscht — siehe
     * `RefreshToken`) und ein neues Access-/Refresh-Token-Paar wird ausgestellt.
     */
    public function testRotatesAndIssuesANewSessionForAValidToken(): void
    {
        $now = new \DateTimeImmutable(self::NOW);
        $token = $this->token();

        $tokens = $this->createMock(RefreshTokenManagerInterface::class);
        $tokens->expects(self::once())->method('findByHash')
            ->willReturnCallback(function (string $hash) use ($token) {
                self::assertSame(hash('sha256', 'raw-refresh-token'), $hash);

                return $token;
            });
        $tokens->expects(self::once())->method('revoke')->with('refresh-1', $now);
        $tokens->expects(self::never())->method('revokeAllForUser');

        $users = $this->createStub(UserManagerInterface::class);
        $users->method('get')->willReturn($this->user());

        $issuedTokens = new IssuedSession('raw-access', $now->modify('+15 minutes'), 'raw-refresh-2', $now->modify('+15 minutes'));
        $issuer = $this->createMock(SessionTokenIssuer::class);
        $issuer->expects(self::once())->method('rotate')->with($token)->willReturn($issuedTokens);

        $session = $this->useCase($tokens, $users, $issuer, $now)->execute('raw-refresh-token');

        self::assertSame($issuedTokens, $session->tokens);
        self::assertSame('erika@example.test', $session->user->email);
    }

    private function useCase(
        RefreshTokenManagerInterface $tokens,
        ?UserManagerInterface $users = null,
        ?SessionTokenIssuer $issuer = null,
        ?\DateTimeImmutable $now = null,
    ): RefreshSessionUseCase {
        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn($now ?? new \DateTimeImmutable(self::NOW));

        $defaultUsers = $this->createStub(UserManagerInterface::class);
        $defaultUsers->method('get')->willReturn($this->user());

        return new RefreshSessionUseCase(
            $tokens,
            $users ?? $defaultUsers,
            $issuer ?? $this->createStub(SessionTokenIssuer::class),
            $clock,
        );
    }

    private function token(
        ?\DateTimeImmutable $expiresAt = null,
        ?\DateTimeImmutable $revokedAt = null,
    ): RefreshToken {
        return new RefreshToken(
            id: 'refresh-1',
            userId: 'user-1',
            tokenHash: hash('sha256', 'raw-refresh-token'),
            expiresAt: $expiresAt ?? new \DateTimeImmutable('2026-06-01T10:15:00+02:00'),
            absoluteExpiresAt: new \DateTimeImmutable('2026-06-01T22:00:00+02:00'),
            createdAt: new \DateTimeImmutable('2026-06-01T09:45:00+02:00'),
            revokedAt: $revokedAt,
        );
    }

    private function user(bool $active = true): User
    {
        return new User(
            id: 'user-1',
            email: 'erika@example.test',
            displayName: 'Erika Musterfrau',
            roles: [],
            moduleAccess: [new ModuleAccess(CmsModule::Pages, ModuleRole::Viewer)],
            active: $active,
            version: 1,
            createdAt: new \DateTimeImmutable('2026-01-01'),
            updatedAt: new \DateTimeImmutable('2026-01-01'),
            lastLoginAt: null,
        );
    }
}
