<?php

namespace App\Logic\IdentityAccess\Session\Query;

use App\Logic\Common\ClockInterface;
use App\Logic\IdentityAccess\Session\Dto\LoginSessionResponse;
use App\Logic\IdentityAccess\Session\Manager\LoginSessionManagerInterface;
use App\Logic\IdentityAccess\Session\Model\LoginSession;
use App\Logic\IdentityAccess\User\Manager\UserManagerInterface;

/**
 * Sitzungshistorie der Redaktion: die jüngsten Anmeldungen mit Person, Verlauf und Ende.
 */
readonly class ListLoginSessionsQuery
{
    public const int DEFAULT_LIMIT = 200;

    public function __construct(
        private LoginSessionManagerInterface $sessions,
        private UserManagerInterface $users,
        private ClockInterface $clock,
    ) {
    }

    /** @return list<LoginSessionResponse> */
    public function execute(int $limit = self::DEFAULT_LIMIT): array
    {
        $now = $this->clock->now();
        $usersById = [];
        foreach ($this->users->all() as $user) {
            $usersById[$user->id] = $user;
        }

        return array_map(
            static function (LoginSession $session) use ($now, $usersById): LoginSessionResponse {
                $user = $usersById[$session->userId] ?? null;

                return new LoginSessionResponse(
                    id: $session->id,
                    userId: $session->userId,
                    userDisplayName: $user?->displayName,
                    userEmail: $user?->email,
                    startedAt: $session->startedAt,
                    lastActivityAt: $session->lastActivityAt,
                    endedAt: $session->effectiveEndedAt($now),
                    endReason: $session->effectiveEndReason($now),
                    userAgent: $session->userAgent,
                );
            },
            $this->sessions->findRecent($limit),
        );
    }
}
