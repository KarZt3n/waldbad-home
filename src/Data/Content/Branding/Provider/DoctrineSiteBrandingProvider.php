<?php

namespace App\Data\Content\Branding\Provider;

use App\Data\Content\Branding\Entity\SiteBrandingEntity;
use App\Data\Content\Branding\Mapper\SiteBrandingMapper;
use App\Logic\Content\Branding\Model\SiteBranding;
use App\Logic\Content\Branding\SiteBrandingProviderInterface;
use Doctrine\ORM\EntityManagerInterface;

readonly class DoctrineSiteBrandingProvider implements SiteBrandingProviderInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private SiteBrandingMapper $mapper,
    ) {
    }

    public function get(): SiteBranding
    {
        $entity = $this->entityManager->find(SiteBrandingEntity::class, SiteBrandingEntity::ID);

        return $entity === null ? new SiteBranding(null) : $this->mapper->toModel($entity);
    }
}
