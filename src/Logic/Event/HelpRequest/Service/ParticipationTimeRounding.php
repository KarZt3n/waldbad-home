<?php

namespace App\Logic\Event\HelpRequest\Service;

/**
 * Rundungsregel für das Ein- und Austragen von Helfern per Klick: Anwesend wird auf das laufende
 * Raster abgerundet (10:14 → 10:00), Austragen auf jedes angebrochene Raster aufgerundet
 * (13:05 → 13:15). Händisch erfasste Zeiten werden nicht gerundet.
 */
readonly class ParticipationTimeRounding
{
    private const int STEP_MINUTES = 15;
    private const int LAST_MINUTE_OF_DAY = 23 * 60 + 59;
    /** Hilfezeiten werden als Ortszeit ohne Zone gespeichert; der Server läuft nicht zwingend in dieser Zone. */
    private const string TIMEZONE = 'Europe/Berlin';

    public function checkInTime(\DateTimeImmutable $now): string
    {
        $minuteOfDay = $this->minuteOfDay($now);

        return $this->format(intdiv($minuteOfDay, self::STEP_MINUTES) * self::STEP_MINUTES);
    }

    /**
     * Liefert mindestens ein volles Raster nach `$checkedInAt`, damit ein sofortiges Austragen
     * keinen leeren Zeitraum erzeugt. Über Mitternacht hinaus wird auf 23:59 begrenzt, da ein
     * Hilfezeitraum am selben Tag enden muss.
     */
    public function checkOutTime(\DateTimeImmutable $now, string $checkedInAt): string
    {
        $roundedUp = (int) ceil($this->minuteOfDay($now) / self::STEP_MINUTES) * self::STEP_MINUTES;
        [$hours, $minutes] = array_map('intval', explode(':', $checkedInAt));
        $earliest = $hours * 60 + $minutes + self::STEP_MINUTES;

        return $this->format(min(max($roundedUp, $earliest), self::LAST_MINUTE_OF_DAY));
    }

    private function minuteOfDay(\DateTimeImmutable $now): int
    {
        $local = $now->setTimezone(new \DateTimeZone(self::TIMEZONE));

        return (int) $local->format('G') * 60 + (int) $local->format('i');
    }

    private function format(int $minuteOfDay): string
    {
        return sprintf('%02d:%02d', intdiv($minuteOfDay, 60), $minuteOfDay % 60);
    }
}
