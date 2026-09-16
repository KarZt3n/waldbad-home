<?php

namespace App\Tests\Unit\Logic\IdentityAccess\Session\UseCase;

use App\Logic\Common\ClockInterface;
use App\Logic\IdentityAccess\LoginToken\Exception\InvalidLoginTokenException;
use App\Logic\IdentityAccess\LoginToken\Manager\LoginTokenManagerInterface;
use App\Logic\IdentityAccess\LoginToken\Model\LoginToken;
use App\Logic\IdentityAccess\Session\Dto\IssuedSession;
use App\Logic\IdentityAccess\Session\Service\SessionTokenIssuer;
use App\Logic\IdentityAccess\Session\UseCase\RedeemLoginTokenUseCase;
use App\Logic\IdentityAccess\User\Manager\UserManagerInterface;
use App\Logic\IdentityAccess\User\Model\CmsModule;
use App\Logic\IdentityAccess\User\Model\ModuleAccess;
use App\Logic\IdentityAccess\User\Model\ModuleRole;
use App\Logic\IdentityAccess\User\Model\User;
use PHPUnit\Framework\TestCase;

final class RedeemLoginTokenUseCaseTest extends TestCase
{
    private const string NOW = '2026-06-01T10:00:00+02:00';

    public function testThrowsForAnUnknownToken(): void
    {
        $tokens = $this->createStub(LoginTokenManagerInterface::class);
        $tokens->method('findByHash')->willReturn(null);

        $this->expectException(InvalidLoginTokenException::class);

        $this->useCase($tokens)->execute('raw-token');
    }

    public function testThrowsForAnExpiredToken(): void
    {
        $tokens = $this->createStub(LoginTokenManagerInterface::class);
        $tokens->method('findByHash')->willReturn($this->token(expiresAt: new \DateTimeImmutable('2026-06-01T09:59:59+02:00')));

        $this->expectException(InvalidLoginTokenException::class);

        $this->useCase($tokens)->execute('raw-token');
    }

    public function testThrowsForAnAlreadyConsumedToken(): void
    {
        $tokens = $this->createStub(LoginTokenManagerInterface::class);
        $tokens->method('findByHash')->willReturn($this->token(consumedAt: new \DateTimeImmutable('2026-06-01T09:00:00+02:00')));

        $this->expectException(InvalidLoginTokenException::class);

        $this->useCase($tokens)->execute('raw-token');
    }

    public function testThrowsWhenNoUserExistsForTheTokenEmail(): void
    {
        $tokens = $this->createStub(LoginTokenManagerInterface::class);
        $tokens->method('findByHash')->willReturn($this->token());
        $users = $this->createStub(UserManagerInterface::class);
        $users->method('findByEmail')->willReturn(null);

        $this->expectException(InvalidLoginTokenException::class);

        $this->useCase($tokens, $users)->execute('raw-token');
    }

    public function testThrowsWhenTheUserIsSuspended(): void
    {
        $tokens = $this->createStub(LoginTokenManagerInterface::class);
        $tokens->method('findByHash')->willReturn($this->token());
        $users = $this->createStub(UserManagerInterface::class);
        $users->method('findByEmail')->willReturn($this->user(active: false));

        $this->expectException(InvalidLoginTokenException::class);

        $this->useCase($tokens, $users)->execute('raw-token');
    }

    /**
     * Kernstück: der Token wird über den SHA-256-Hash nachgeschlagen, als eingelöst markiert
     * (kann kein zweites Mal verwendet werden), `lastLoginAt` wird auf jetzt gesetzt, und ein neues
     * Access-/Refresh-Token-Paar wird für den Benutzer ausgestellt.
     */
    public function testMarksTheTokenConsumedRecordsLoginAndIssuesASession(): void
    {
        $now = new \DateTimeImmutable(self::NOW);

        $tokens = $this->createMock(LoginTokenManagerInterface::class);
        $tokens->expects(self::once())->method('findByHash')
            ->willReturnCallback(function (string $hash) {
                self::assertSame(hash('sha256', 'raw-token'), $hash);

                return $this->token();
            });
        $tokens->expects(self::once())->method('markConsumed')->with('token-1', $now);

        $savedUser = null;
        $users = $this->createMock(UserManagerInterface::class);
        $users->method('findByEmail')->willReturn($this->user());
        $users->expects(self::once())->method('save')->willReturnCallback(
            function (User $user) use (&$savedUser): User {
                $savedUser = $user;

                return $user;
            },
        );

        $issuedTokens = new IssuedSession('raw-access', $now->modify('+15 minutes'), 'raw-refresh', $now->modify('+15 minutes'));
        $issuer = $this->createMock(SessionTokenIssuer::class);
        $issuer->expects(self::once())->method('issueInitial')->with('user-1')->willReturn($issuedTokens);

        $session = $this->useCase($tokens, $users, $issuer, $now)->execute('raw-token');

        self::assertNotNull($savedUser);
        self::assertEquals($now, $savedUser->lastLoginAt);
        self::assertSame($issuedTokens, $session->tokens);
        self::assertSame('erika@example.test', $session->user->email);
    }

    private function useCase(
        LoginTokenManagerInterface $tokens,
        ?UserManagerInterface $users = null,
        ?SessionTokenIssuer $issuer = null,
        ?\DateTimeImmutable $now = null,
    ): RedeemLoginTokenUseCase {
        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn($now ?? new \DateTimeImmutable(self::NOW));

        $defaultUsers = $this->createStub(UserManagerInterface::class);
        $defaultUsers->method('findByEmail')->willReturn($this->user());
        $defaultUsers->method('save')->willReturnArgument(0);

        return new RedeemLoginTokenUseCase(
            $tokens,
            $users ?? $defaultUsers,
            $issuer ?? $this->createStub(SessionTokenIssuer::class),
            $clock,
        );
    }

    private function token(?\DateTimeImmutable $expiresAt = null, ?\DateTimeImmutable $consumedAt = null): LoginToken
    {
        return new LoginToken(
            id: 'token-1',
            email: 'erika@example.test',
            tokenHash: hash('sha256', 'raw-token'),
            expiresAt: $expiresAt ?? new \DateTimeImmutable('2026-06-01T10:05:00+02:00'),
            consumedAt: $consumedAt,
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
