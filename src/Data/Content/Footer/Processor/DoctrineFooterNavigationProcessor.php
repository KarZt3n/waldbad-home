<?php

namespace App\Data\Content\Footer\Processor;

use App\Data\Content\Footer\Entity\FooterNavigationEntity;
use App\Data\Content\Footer\Mapper\FooterNavigationMapper;
use App\Logic\Common\ClockInterface;
use App\Logic\Content\Footer\FooterNavigationProcessorInterface;
use App\Logic\Content\Footer\Model\FooterNavigation;
use Doctrine\ORM\EntityManagerInterface;

readonly class DoctrineFooterNavigationProcessor implements FooterNavigationProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private FooterNavigationMapper $mapper,
        private ClockInterface $clock,
    ) {
    }

    public function save(FooterNavigation $navigation): FooterNavigation
    {
        $entity = $this->entityManager->find(FooterNavigationEntity::class, FooterNavigationEntity::ID);
        $now = $this->clock->now();
        if ($entity === null) {
            $entity = $this->mapper->createEntity($navigation, $now);
            $this->entityManager->persist($entity);
        } else {
            $this->mapper->updateEntity($navigation, $entity, $now);
        }
        $this->entityManager->flush();

        return $this->mapper->toModel($entity);
    }
}
