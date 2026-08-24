<?php

namespace App\Logic\Event\Activity\Model;

use App\Logic\Common\Exception\BusinessRuleViolationException;

readonly class EventActivity
{
    public function __construct(
        public string $id,
        public string $name,
        public string $description,
        public bool $active,
        public \DateTimeImmutable $createdAt,
        public \DateTimeImmutable $updatedAt,
    ) {
        if (trim($this->name) === '' || mb_strlen($this->name) > 120) {
            throw new BusinessRuleViolationException('Der Aktivitätsname muss zwischen 1 und 120 Zeichen lang sein.');
        }
        if (mb_strlen($this->description) > 1000) {
            throw new BusinessRuleViolationException('Die Aktivitätsbeschreibung darf höchstens 1000 Zeichen lang sein.');
        }
    }

    public function update(string $name, string $description, bool $active, \DateTimeImmutable $updatedAt): self
    {
        return new self(
            id: $this->id,
            name: trim($name),
            description: trim($description),
            active: $active,
            createdAt: $this->createdAt,
            updatedAt: $updatedAt,
        );
    }
}
