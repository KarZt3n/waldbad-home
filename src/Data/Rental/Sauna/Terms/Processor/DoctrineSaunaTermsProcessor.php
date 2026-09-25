<?php

namespace App\Data\Rental\Sauna\Terms\Processor;

use App\Data\Rental\Sauna\Terms\Entity\SaunaTermsEntity;
use App\Data\Rental\Sauna\Terms\Mapper\SaunaTermsMapper;
use App\Logic\Rental\Sauna\Terms\Model\SaunaTerms;
use App\Logic\Rental\Sauna\Terms\SaunaTermsProcessorInterface;
use Doctrine\ORM\EntityManagerInterface;

readonly class DoctrineSaunaTermsProcessor implements SaunaTermsProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private SaunaTermsMapper $mapper,
    ) {
    }

    public function save(SaunaTerms $terms): SaunaTerms
    {
        $entity = $this->entityManager->find(SaunaTermsEntity::class, SaunaTermsEntity::SINGLETON_ID);
        if ($entity === null) {
            $entity = $this->mapper->createEntity($terms);
            $this->entityManager->persist($entity);
        } else {
            $this->mapper->updateEntity($terms, $entity);
        }
        $this->entityManager->flush();

        return $this->mapper->toModel($entity);
    }
}
