<?php

namespace App\Logic\Content\Page\Query;

use App\Logic\Content\Page\Manager\PageManagerInterface;

readonly class GetNavigationQuery
{
    public function __construct(private PageManagerInterface $manager)
    {
    }

    /**
     * @return list<array{id: string, label: string, slug: string, parentId: string|null}>
     */
    public function execute(): array
    {
        return array_map(
            static fn ($page): array => [
                'id' => $page->id,
                'label' => $page->navigationLabel,
                'slug' => $page->slug,
                'parentId' => $page->parentId,
            ],
            $this->manager->navigation(),
        );
    }
}
