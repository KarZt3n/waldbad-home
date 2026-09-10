<?php

namespace App\Data\Settings\MailSignature\Provider;

use App\Data\Settings\MailSignature\Entity\MailSignatureEntity;
use App\Data\Settings\MailSignature\Mapper\MailSignatureMapper;
use App\Logic\Settings\MailSignature\MailSignatureProviderInterface;
use App\Logic\Settings\MailSignature\Model\MailSignature;
use Doctrine\ORM\EntityManagerInterface;

readonly class DoctrineMailSignatureProvider implements MailSignatureProviderInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private MailSignatureMapper $mapper,
    ) {
    }

    public function find(string $id): ?MailSignature
    {
        $entity = $this->entityManager->find(MailSignatureEntity::class, $id);

        return $entity === null ? null : $this->mapper->toModel($entity);
    }

    public function findAll(): array
    {
        $entities = $this->entityManager->getRepository(MailSignatureEntity::class)->findBy([], ['name' => 'ASC']);

        return array_map($this->mapper->toModel(...), $entities);
    }
}
