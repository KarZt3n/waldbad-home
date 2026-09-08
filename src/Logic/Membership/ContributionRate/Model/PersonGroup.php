<?php

namespace App\Logic\Membership\ContributionRate\Model;

/**
 * Für welchen Personenkreis ein Beitragssatz gilt. Anders als die feste `ContributionCategory`
 * (mit fest im Code hinterlegter Bedeutung für die laufende Beitragsberechnung) ist dies ein rein
 * im Admin definierbares Merkmal — z. B. um eine einmalige Beitrittsgebühr für Einzelpersonen und
 * eine andere für Familien anzulegen, ohne dafür eine neue feste Kategorie im Code zu benötigen.
 */
enum PersonGroup: string
{
    case Individual = 'individual';
    case Family = 'family';
}
