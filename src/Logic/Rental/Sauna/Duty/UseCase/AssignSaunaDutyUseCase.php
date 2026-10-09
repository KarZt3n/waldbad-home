<?php

namespace App\Logic\Rental\Sauna\Duty\UseCase;

use App\Logic\Common\Exception\BusinessRuleViolationException;
use App\Logic\Common\IdentifierGeneratorInterface;
use App\Logic\Rental\Sauna\Duty\Dto\SaunaDutyPoint;
use App\Logic\Rental\Sauna\Duty\Dto\SaunaDutyResponse;
use App\Logic\Rental\Sauna\Duty\Manager\SaunaDutyManagerInterface;
use App\Logic\Rental\Sauna\Duty\Model\SaunaDuty;
use App\Logic\Rental\Sauna\Duty\Model\SaunaDutyPointState;
use App\Logic\Rental\Sauna\Duty\SaunaDutyMemberDirectoryInterface;
use App\Logic\Rental\Sauna\Duty\Service\SaunaDutyRoster;

/** Trägt ein Mitglied zu einem Dienstzeitpunkt (Einlass, Übergang, Abschließen) ein; mehrere Personen je Zeitpunkt sind möglich. */
readonly class AssignSaunaDutyUseCase
{
    public function __construct(
        private SaunaDutyRoster $roster,
        private SaunaDutyManagerInterface $duties,
        private SaunaDutyMemberDirectoryInterface $directory,
        private IdentifierGeneratorInterface $identifierGenerator,
    ) {
    }

    public function execute(\DateTimeImmutable $date, string $time, string $memberId): SaunaDutyResponse
    {
        $point = array_find(
            $this->roster->pointsOn($date),
            static fn (SaunaDutyPoint $point): bool => $point->time === $time && $point->state !== SaunaDutyPointState::Unscheduled,
        ) ?? throw new BusinessRuleViolationException(sprintf('Am %s ist um %s Uhr kein Saunadienst vorgesehen.', $date->format('d.m.Y'), $time));
        $member = $this->directory->find($memberId)
            ?? throw new BusinessRuleViolationException('Das ausgewählte Mitglied wurde nicht gefunden.');
        foreach ($point->duties as $duty) {
            if ($duty->memberId === $member->memberId) {
                throw new BusinessRuleViolationException(sprintf('%s %s ist zu dieser Zeit bereits eingetragen.', $member->firstName, $member->lastName));
            }
        }

        return SaunaDutyResponse::fromDuty($this->duties->save(new SaunaDuty(
            id: $this->identifierGenerator->generate(),
            date: $date->setTime(0, 0),
            time: $time,
            memberId: $member->memberId,
            memberNumber: $member->memberNumber,
            firstName: $member->firstName,
            lastName: $member->lastName,
        )));
    }
}
