<?php

namespace App\Data\Rental\Sauna\Terms\Provider;

use App\Data\Rental\Sauna\Terms\Entity\SaunaTermsEntity;
use App\Data\Rental\Sauna\Terms\Mapper\SaunaTermsMapper;
use App\Logic\Rental\Sauna\Terms\Model\SaunaTerms;
use App\Logic\Rental\Sauna\Terms\SaunaTermsProviderInterface;
use Doctrine\ORM\EntityManagerInterface;

readonly class DoctrineSaunaTermsProvider implements SaunaTermsProviderInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private SaunaTermsMapper $mapper,
    ) {
    }

    public function find(): ?SaunaTerms
    {
        $entity = $this->entityManager->find(SaunaTermsEntity::class, SaunaTermsEntity::SINGLETON_ID);

        return $entity === null ? null : $this->mapper->toModel($entity);
    }
}
