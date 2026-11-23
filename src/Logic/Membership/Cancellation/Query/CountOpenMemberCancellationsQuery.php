<?php

namespace App\Logic\Membership\Cancellation\Query;

use App\Logic\Membership\Cancellation\Manager\MemberCancellationManagerInterface;

/** Anzahl offener Kündigungen (Zahl am Reiter der Mitgliederverwaltung). */
readonly class CountOpenMemberCancellationsQuery
{
    public function __construct(private MemberCancellationManagerInterface $manager)
    {
    }

    public function execute(): int
    {
        return $this->manager->countOpen();
    }
}
