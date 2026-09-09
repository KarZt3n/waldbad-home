<?php

namespace App\Tests\Support;

use App\Logic\Common\EmailDeliverabilityCheckerInterface;

/**
 * Ersetzt im Testumfeld den echten, DNS-abfragenden Checker (`EguliasEmailDeliverabilityChecker`,
 * siehe config/services_test.yaml) — Tests laufen ohne verlässige Netzwerkverbindung und verwenden
 * ohnehin die reservierte Testdomain `*.test`, die bei einer echten DNS-Prüfung immer als nicht
 * zustellbar gelten würde. Über die eigens reservierte Domain `@notdeliverable.test` lässt sich der
 * Ablehnungspfad trotzdem gezielt testen.
 */
readonly class FakeEmailDeliverabilityChecker implements EmailDeliverabilityCheckerInterface
{
    public function isDeliverable(string $email): bool
    {
        return !str_ends_with(strtolower($email), '@notdeliverable.test');
    }
}
