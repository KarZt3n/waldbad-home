<?php

namespace App\Logic\Membership\WorkAssignment;

use App\Logic\Membership\WorkAssignment\Model\WorkAssignmentParticipation;

/**
 * Adapter zur Helferanmeldung („Ich möchte helfen!“): liefert die erfassten Teilnahmen von
 * Mitgliedern, ohne dass die Mitgliederverwaltung die internen Strukturen des Veranstaltungsmoduls
 * kennt.
 */
interface MemberWorkAssignmentProviderInterface
{
    /**
     * @param list<string> $memberIds
     * @return list<WorkAssignmentParticipation> neueste Veranstaltung zuerst
     */
    public function findForMembers(array $memberIds): array;
}
