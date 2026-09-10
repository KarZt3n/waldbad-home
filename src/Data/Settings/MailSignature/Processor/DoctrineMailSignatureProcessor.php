<?php

namespace App\Data\Settings\MailSignature\Processor;

use App\Data\Settings\MailSignature\Entity\MailSignatureEntity;
use App\Data\Settings\MailSignature\Mapper\MailSignatureMapper;
use App\Logic\Settings\MailSignature\Exception\MailSignatureNotFoundException;
use App\Logic\Settings\MailSignature\MailSignatureProcessorInterface;
use App\Logic\Settings\MailSignature\Model\MailSignature;
use Doctrine\ORM\EntityManagerInterface;

readonly class DoctrineMailSignatureProcessor implements MailSignatureProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private MailSignatureMapper $mapper,
    ) {
    }

    public function save(MailSignature $signature): MailSignature
    {
        $entity = $this->entityManager->find(MailSignatureEntity::class, $signature->id);
        if ($entity === null) {
            $entity = $this->mapper->createEntity($signature);
            $this->entityManager->persist($entity);
        } else {
            $this->mapper->updateEntity($signature, $entity);
        }
        $this->entityManager->flush();

        return $this->mapper->toModel($entity);
    }

    public function delete(string $id): void
    {
        $entity = $this->entityManager->find(MailSignatureEntity::class, $id);
        if ($entity === null) {
            throw new MailSignatureNotFoundException($id);
        }
        $this->entityManager->remove($entity);
        $this->entityManager->flush();
    }
}
