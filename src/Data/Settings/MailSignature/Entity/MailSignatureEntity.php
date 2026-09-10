<?php

namespace App\Data\Settings\MailSignature\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'mail_signature')]
class MailSignatureEntity
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: Types::STRING, length: 36)]
        private string $id,
        #[ORM\Column(type: Types::STRING, length: 180)]
        private string $name,
        #[ORM\Column(type: Types::TEXT)]
        private string $body,
    ) {
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function update(string $name, string $body): void
    {
        $this->name = $name;
        $this->body = $body;
    }
}
