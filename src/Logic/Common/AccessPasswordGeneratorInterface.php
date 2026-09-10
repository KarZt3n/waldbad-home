<?php

namespace App\Logic\Common;

/**
 * Erzeugt ein kurzes, von Hand abtippbares Zufallspasswort (im Unterschied zu
 * `SecureTokenGeneratorInterface`, das ein hochentropisches Geheimnis für eine URL erzeugt) — zweiter
 * Faktor neben dem per Link verschickten `MemberAccessToken`: der Link allein kann über
 * Vorschau-Crawler, Weiterleitungen oder die Browser-Historie leaken, das separat in der Mail
 * genannte Passwort nicht.
 */
interface AccessPasswordGeneratorInterface
{
    public function generate(): string;
}
