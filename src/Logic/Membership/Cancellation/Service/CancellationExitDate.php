<?php

namespace App\Logic\Membership\Cancellation\Service;

/**
 * Laut Satzung ist ein Austritt nur zum 31.12. möglich, mit einer Frist von drei Monaten zum
 * Jahresende: Bis einschließlich 30.09. eingegangen gilt der 31.12. desselben Jahres, danach der
 * 31.12. des Folgejahres.
 */
final class CancellationExitDate
{
    public static function for(\DateTimeImmutable $submittedAt): \DateTimeImmutable
    {
        $year = (int) $submittedAt->format('Y');
        if ($submittedAt->format('m-d') > '09-30') {
            ++$year;
        }

        return new \DateTimeImmutable(sprintf('%d-12-31', $year), $submittedAt->getTimezone());
    }
}
