<?php

namespace App\Data\Membership\Member\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'member_contribution_charge')]
#[ORM\Index(name: 'idx_member_contribution_charge_member_charged', columns: ['member_id', 'charged_at'])]
class MemberContributionChargeEntity
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: Types::STRING, length: 36)]
        private string $id,
        #[ORM\ManyToOne(targetEntity: MemberEntity::class, inversedBy: 'oneTimeCharges')]
        #[ORM\JoinColumn(name: 'member_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
        private MemberEntity $member,
        #[ORM\Column(type: Types::STRING, length: 180)]
        private string $label,
        #[ORM\Column(type: Types::INTEGER)]
        private int $amountCents,
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
        private \DateTimeImmutable $chargedAt,
    ) {
    }

    public function getId(): string { return $this->id; }
    public function getLabel(): string { return $this->label; }
    public function getAmountCents(): int { return $this->amountCents; }
    public function getChargedAt(): \DateTimeImmutable { return $this->chargedAt; }
}
