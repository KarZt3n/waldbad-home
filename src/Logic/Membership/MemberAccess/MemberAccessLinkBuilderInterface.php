<?php

namespace App\Logic\Membership\MemberAccess;

/**
 * Baut die vollständige, klickbare URL zu „Meine Mitgliedschaft“ (mit oder ohne Token) — die
 * eigentliche URL-Erzeugung (Host/Schema aus dem aktuellen Request, Routing) ist Infrastruktur
 * (siehe `RoutingMemberAccessLinkBuilder` in der UI-Schicht), die Logic-Schicht bleibt dadurch ohne
 * Symfony-Routing-Abhängigkeit testbar.
 */
interface MemberAccessLinkBuilderInterface
{
    public function build(string $token): string;

    /**
     * Einstiegsseite ohne Token, auf der ein Zugang angefordert werden kann (z. B. als Verweis in
     * der Bestätigungsmail eines angenommenen Mitgliedsantrags).
     */
    public function buildEntry(): string;
}
