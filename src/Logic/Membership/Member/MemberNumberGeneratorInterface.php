<?php

namespace App\Logic\Membership\Member;

interface MemberNumberGeneratorInterface
{
    /**
     * Vergibt die nächste freie Mitgliedsnummer (Format „Bad-01234“) aus einer atomaren Sequenz.
     */
    public function next(): string;
}
