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

/**
 * Trägt ein Mitglied zu allen Dienstzeitpunkten eines Tages ein, statt jeden Zeitpunkt einzeln zu
 * belegen. Zeitpunkte, zu denen die Person schon eingetragen ist, werden übersprungen.
 */
readonly class AssignSaunaDutyForDayUseCase
{
    public function __construct(
        private SaunaDutyRoster $roster,
        private SaunaDutyManagerInterface $duties,
        private SaunaDutyMemberDirectoryInterface $directory,
        private IdentifierGeneratorInterface $identifierGenerator,
    ) {
    }

    /** @return list<SaunaDutyResponse> */
    public function execute(\DateTimeImmutable $date, string $memberId): array
    {
        $points = array_values(array_filter(
            $this->roster->pointsOn($date),
            static fn (SaunaDutyPoint $point): bool => $point->state !== SaunaDutyPointState::Unscheduled,
        ));
        if ($points === []) {
            throw new BusinessRuleViolationException(sprintf('Am %s ist kein Saunadienst vorgesehen.', $date->format('d.m.Y')));
        }
        $member = $this->directory->find($memberId)
            ?? throw new BusinessRuleViolationException('Das ausgewählte Mitglied wurde nicht gefunden.');

        $openPoints = array_values(array_filter(
            $points,
            static fn (SaunaDutyPoint $point): bool => array_find(
                $point->duties,
                static fn (SaunaDutyResponse $duty): bool => $duty->memberId === $member->memberId,
            ) === null,
        ));
        if ($openPoints === []) {
            throw new BusinessRuleViolationException(sprintf('%s %s ist an diesem Tag bereits zu allen Zeiten eingetragen.', $member->firstName, $member->lastName));
        }

        return array_map(fn (SaunaDutyPoint $point): SaunaDutyResponse => SaunaDutyResponse::fromDuty($this->duties->save(new SaunaDuty(
            id: $this->identifierGenerator->generate(),
            date: $date->setTime(0, 0),
            time: $point->time,
            memberId: $member->memberId,
            memberNumber: $member->memberNumber,
            firstName: $member->firstName,
            lastName: $member->lastName,
        ))), $openPoints);
    }
}
