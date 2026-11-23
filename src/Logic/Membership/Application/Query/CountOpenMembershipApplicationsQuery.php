<?php

namespace App\Logic\Membership\Application\Query;

use App\Logic\Membership\Application\Manager\MembershipApplicationManagerInterface;

/** Anzahl unbearbeiteter Mitgliedsanträge (Zahl am Reiter der Mitgliederverwaltung). */
readonly class CountOpenMembershipApplicationsQuery
{
    public function __construct(private MembershipApplicationManagerInterface $manager)
    {
    }

    public function execute(): int
    {
        return $this->manager->countOpen();
    }
}
