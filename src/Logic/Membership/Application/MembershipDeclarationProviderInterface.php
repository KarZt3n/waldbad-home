<?php

namespace App\Logic\Membership\Application;

use App\Logic\Membership\Application\Model\MembershipDeclaration;

/**
 * Adapter auf die Texte der Seitenerweiterung „Mitgliedsantrag“ (Modul Content), die Redakteure unter
 * „Seiten“ → „Erweiterungen“ pflegen.
 */
interface MembershipDeclarationProviderInterface
{
    public function current(): MembershipDeclaration;
}
