<?php

namespace App\Logic\Common;

/**
 * Erzeugt ein hochentropisches, für sich allein unerratbares Geheimnis — im Unterschied zu
 * `IdentifierGeneratorInterface` (erzeugt IDs, die nicht geheim sein müssen) für Werte, die selbst
 * als Zugangsberechtigung dienen, z. B. einen per E-Mail verschickten Zugangs-Link (siehe
 * `MemberAccessToken`).
 */
interface SecureTokenGeneratorInterface
{
    public function generate(): string;
}
