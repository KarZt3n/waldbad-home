<?php

namespace App\Logic\Membership\ContributionRate\Model;

/**
 * Feste Kategorien der Beitragsordnung, über die die automatische Beitragsermittlung
 * (`MemberContributionCalculator`) einen passenden Beitragssatz nachschlägt. Die Kategorien selbst
 * sind fix (siehe Migration `Version20260907120100`); Bezeichnung, Betrag und Zeitraum je Kategorie
 * bleiben admin-seitig editierbar.
 */
enum ContributionCategory: string
{
    case IndividualJunior = 'individual_junior';
    case IndividualSenior = 'individual_senior';
    case FamilyAdult = 'family_adult';
    case FamilyChildPaying = 'family_child_paying';
    case FamilyChildExempt = 'family_child_exempt';

    /**
     * Zuschlag zzgl. zum eigentlichen Mitgliedsbeitrag für Mitglieder in einer bestimmten
     * Altersspanne (siehe `ContributionRate::$minAge`/`$maxAge`), z. B. der Arbeitseinsatz-
     * Ausgleich, der nach Ableistung von Gemeinschaftsstunden erstattet wird.
     */
    case WorkAssignmentSurcharge = 'work_assignment_surcharge';
}
