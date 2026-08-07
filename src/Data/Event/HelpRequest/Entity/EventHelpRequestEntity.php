<?php

namespace App\Data\Event\HelpRequest\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'event_help_request')]
#[ORM\Index(name: 'idx_event_help_event_status', columns: ['event_identifier', 'status'])]
#[ORM\Index(name: 'idx_event_help_submitted', columns: ['submitted_at'])]
class EventHelpRequestEntity
{
    /** @var Collection<int, EventHelpIntervalEntity> */
    #[ORM\OneToMany(targetEntity: EventHelpIntervalEntity::class, mappedBy: 'request', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $participationIntervals;

    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: Types::STRING, length: 36)]
        private string $id,
        #[ORM\Column(type: Types::STRING, length: 80)]
        private string $eventIdentifier,
        #[ORM\Column(type: Types::STRING, length: 180)]
        private string $eventTitle,
        #[ORM\Column(type: Types::STRING, length: 10)]
        private string $eventDate,
        #[ORM\Column(type: Types::STRING, length: 5)]
        private string $eventTime,
        #[ORM\Column(type: Types::STRING, length: 120)]
        private string $firstName,
        #[ORM\Column(type: Types::STRING, length: 120)]
        private string $lastName,
        #[ORM\Column(type: Types::TEXT)]
        private string $message,
        #[ORM\Column(type: Types::STRING, length: 20)]
        private string $status,
        #[ORM\Column(type: Types::INTEGER, nullable: true)]
        private ?int $participationMinutes,
        #[ORM\Column(name: 'participation_from_time', type: Types::STRING, length: 5, nullable: true)]
        private ?string $legacyParticipationFromTime,
        #[ORM\Column(name: 'participation_to_time', type: Types::STRING, length: 5, nullable: true)]
        private ?string $legacyParticipationToTime,
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
        private \DateTimeImmutable $submittedAt,
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
        private \DateTimeImmutable $updatedAt,
    ) {
        $this->participationIntervals = new ArrayCollection();
    }

    public function getId(): string { return $this->id; }
    public function getEventIdentifier(): string { return $this->eventIdentifier; }
    public function getEventTitle(): string { return $this->eventTitle; }
    public function getEventDate(): string { return $this->eventDate; }
    public function getEventTime(): string { return $this->eventTime; }
    public function getFirstName(): string { return $this->firstName; }
    public function getLastName(): string { return $this->lastName; }
    public function getMessage(): string { return $this->message; }
    public function getStatus(): string { return $this->status; }
    public function getParticipationMinutes(): ?int { return $this->participationMinutes; }
    /** @return list<EventHelpIntervalEntity> */
    public function getParticipationIntervals(): array { return array_values($this->participationIntervals->toArray()); }

    /** @param list<EventHelpIntervalEntity> $intervals */
    public function replaceParticipationIntervals(array $intervals): void
    {
        $this->participationIntervals->clear();
        foreach ($intervals as $interval) {
            $this->participationIntervals->add($interval);
        }
    }
    public function getSubmittedAt(): \DateTimeImmutable { return $this->submittedAt; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }

    public function changeParticipation(
        string $status,
        ?int $participationMinutes,
        \DateTimeImmutable $updatedAt,
    ): void
    {
        $this->status = $status;
        $this->participationMinutes = $participationMinutes;
        $this->updatedAt = $updatedAt;
    }
}
