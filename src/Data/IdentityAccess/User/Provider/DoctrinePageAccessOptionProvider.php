<?php

namespace App\Data\IdentityAccess\User\Provider;

use App\Data\Content\Page\Entity\PageEntity;
use App\Logic\IdentityAccess\User\Dto\PageAccessOption;
use App\Logic\IdentityAccess\User\PageAccessOptionProviderInterface;
use Doctrine\ORM\EntityManagerInterface;

readonly class DoctrinePageAccessOptionProvider implements PageAccessOptionProviderInterface
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function findAll(): array
    {
        $entities = $this->entityManager->getRepository(PageEntity::class)->findBy([], [
            'navigationPosition' => 'ASC',
            'title' => 'ASC',
        ]);

        return array_map(
            static fn (PageEntity $page): PageAccessOption => new PageAccessOption(
                id: $page->getId(),
                title: $page->getTitle(),
                parentId: $page->getParentId(),
                navigationPosition: $page->getNavigationPosition(),
            ),
            $entities,
        );
    }
}
