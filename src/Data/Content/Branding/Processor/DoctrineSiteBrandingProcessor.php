<?php

namespace App\Data\Content\Branding\Processor;

use App\Data\Content\Branding\Entity\SiteBrandingEntity;
use App\Data\Content\Branding\Mapper\SiteBrandingMapper;
use App\Logic\Common\ClockInterface;
use App\Logic\Content\Branding\Model\SiteBranding;
use App\Logic\Content\Branding\SiteBrandingProcessorInterface;
use Doctrine\ORM\EntityManagerInterface;

readonly class DoctrineSiteBrandingProcessor implements SiteBrandingProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private SiteBrandingMapper $mapper,
        private ClockInterface $clock,
    ) {
    }

    public function save(SiteBranding $branding): SiteBranding
    {
        $entity = $this->entityManager->find(SiteBrandingEntity::class, SiteBrandingEntity::ID);
        $now = $this->clock->now();
        if ($entity === null) {
            $entity = $this->mapper->createEntity($branding, $now);
            $this->entityManager->persist($entity);
        } else {
            $this->mapper->updateEntity($branding, $entity, $now);
        }
        $this->entityManager->flush();

        return $this->mapper->toModel($entity);
    }
}
