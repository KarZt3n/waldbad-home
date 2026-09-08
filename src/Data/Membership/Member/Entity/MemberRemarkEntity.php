<?php

namespace App\Data\Membership\Member\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'member_remark')]
#[ORM\Index(name: 'idx_member_remark_member_created', columns: ['member_id', 'created_at'])]
class MemberRemarkEntity
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: Types::STRING, length: 36)]
        private string $id,
        #[ORM\ManyToOne(targetEntity: MemberEntity::class, inversedBy: 'remarks')]
        #[ORM\JoinColumn(name: 'member_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
        private MemberEntity $member,
        #[ORM\Column(type: Types::TEXT)]
        private string $text,
        #[ORM\Column(type: Types::STRING, length: 180, nullable: true)]
        private ?string $authorDisplayName,
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
        private \DateTimeImmutable $createdAt,
    ) {
    }

    public function getId(): string { return $this->id; }
    public function getText(): string { return $this->text; }
    public function getAuthorDisplayName(): ?string { return $this->authorDisplayName; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
