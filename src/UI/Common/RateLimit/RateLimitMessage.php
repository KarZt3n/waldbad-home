<?php

namespace App\UI\Common\RateLimit;

/**
 * Einheitlicher Text für `TooManyRequestsHttpException`, sobald ein Rate-Limiter
 * (siehe `config/packages/framework.yaml`) anschlägt: benennt explizit, dass das Limit
 * erreicht wurde, und wie viele Minuten bis zum nächsten Versuch zu warten sind.
 */
final class RateLimitMessage
{
    public static function exceeded(int $retryAfterSeconds, bool $formal = false): string
    {
        $minutes = max(1, (int) ceil($retryAfterSeconds / 60));
        $unit = $minutes === 1 ? 'Minute' : 'Minuten';

        return $formal
            ? sprintf('Sie haben die maximale Anzahl an Anfragen erreicht. Bitte versuchen Sie es in %d %s erneut.', $minutes, $unit)
            : sprintf('Du hast die maximale Anzahl an Anfragen erreicht. Bitte versuche es in %d %s erneut.', $minutes, $unit);
    }
}
