<?php

namespace App\Logic\Membership\MemberMessage\Query;

use App\Logic\Membership\MemberMessage\Manager\MemberMessageManagerInterface;

/** Anzahl nicht erledigter Mitgliedernachrichten (Zahl am Reiter der Mitgliederverwaltung). */
readonly class CountOpenMemberMessagesQuery
{
    public function __construct(private MemberMessageManagerInterface $manager)
    {
    }

    public function execute(): int
    {
        return $this->manager->countOpen();
    }
}
