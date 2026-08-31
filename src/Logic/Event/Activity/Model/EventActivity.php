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
        public ?int $defaultRequiredHelpers,
        public \DateTimeImmutable $createdAt,
        public \DateTimeImmutable $updatedAt,
    ) {
        if (trim($this->name) === '' || mb_strlen($this->name) > 120) {
            throw new BusinessRuleViolationException('Der Aktivitätsname muss zwischen 1 und 120 Zeichen lang sein.');
        }
        if (mb_strlen($this->description) > 1000) {
            throw new BusinessRuleViolationException('Die Aktivitätsbeschreibung darf höchstens 1000 Zeichen lang sein.');
        }
        if ($this->defaultRequiredHelpers !== null && ($this->defaultRequiredHelpers < 1 || $this->defaultRequiredHelpers > 999)) {
            throw new BusinessRuleViolationException('Die Standard-Helferzahl muss zwischen 1 und 999 liegen.');
        }
    }

    public function update(string $name, string $description, bool $active, ?int $defaultRequiredHelpers, \DateTimeImmutable $updatedAt): self
    {
        return new self(
            id: $this->id,
            name: trim($name),
            description: trim($description),
            active: $active,
            defaultRequiredHelpers: $defaultRequiredHelpers,
            createdAt: $this->createdAt,
            updatedAt: $updatedAt,
        );
    }
}
