<?php

namespace App\Data\Settings\MailTemplate\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'mail_template')]
class MailTemplateEntity
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'template_key', type: Types::STRING, length: 60)]
        private string $key,
        #[ORM\Column(type: Types::STRING, length: 255)]
        private string $subject,
        #[ORM\Column(type: Types::TEXT)]
        private string $body,
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
        private ?\DateTimeImmutable $updatedAt,
    ) {
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function getSubject(): string
    {
        return $this->subject;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function update(string $subject, string $body, \DateTimeImmutable $updatedAt): void
    {
        $this->subject = $subject;
        $this->body = $body;
        $this->updatedAt = $updatedAt;
    }
}
