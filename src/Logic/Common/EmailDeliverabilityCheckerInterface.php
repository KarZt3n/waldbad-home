<?php

namespace App\Logic\Common;

interface EmailDeliverabilityCheckerInterface
{
    /**
     * Ob $email nicht nur syntaktisch gültig ist, sondern die Domain auch tatsächlich E-Mails
     * annehmen kann (gültiger MX- bzw. ersatzweise A-/AAAA-Eintrag) — mehr als ein reiner
     * Regex-/Format-Check, aber bewusst kein SMTP-Dialog mit dem Zielserver („Postfach existiert
     * wirklich“): Die meisten Anbieter unterbinden oder verfälschen solche Prüfungen inzwischen
     * (Catch-All-Annahme, Greylisting), sie wären also ohnehin nicht zuverlässig.
     */
    public function isDeliverable(string $email): bool;
}
