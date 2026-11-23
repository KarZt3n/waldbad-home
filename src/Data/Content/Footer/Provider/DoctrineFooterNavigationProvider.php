<?php

namespace App\Data\Content\Footer\Provider;

use App\Data\Content\Footer\Entity\FooterNavigationEntity;
use App\Data\Content\Footer\Mapper\FooterNavigationMapper;
use App\Logic\Content\Footer\FooterNavigationProviderInterface;
use App\Logic\Content\Footer\Model\FooterNavigation;
use Doctrine\ORM\EntityManagerInterface;

readonly class DoctrineFooterNavigationProvider implements FooterNavigationProviderInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private FooterNavigationMapper $mapper,
    ) {
    }

    public function get(): ?FooterNavigation
    {
        $entity = $this->entityManager->find(FooterNavigationEntity::class, FooterNavigationEntity::ID);

        return $entity === null ? null : $this->mapper->toModel($entity);
    }
}
