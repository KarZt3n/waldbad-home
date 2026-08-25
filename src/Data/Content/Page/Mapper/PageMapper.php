<?php

namespace App\Data\Content\Page\Mapper;

use App\Data\Content\Page\Entity\PageEntity;
use App\Data\Content\Page\Entity\PublishedPageEntity;
use App\Logic\Content\Page\Model\ContentBlock;
use App\Logic\Content\Page\Model\ContentBlockType;
use App\Logic\Content\Page\Model\ContentCollectionItem;
use App\Logic\Content\Page\Model\EventActivityAssignment;
use App\Logic\Content\Page\Model\EventCallToAction;
use App\Logic\Content\Page\Model\Page;
use App\Logic\Content\Page\Model\PageStatus;

readonly class PageMapper
{
    public function toModel(PageEntity $entity): Page
    {
        return new Page(
            id: $entity->getId(),
            title: $entity->getTitle(),
            slug: $entity->getSlug(),
            navigationLabel: $entity->getNavigationLabel(),
            parentId: $entity->getParentId(),
            blocks: array_map(
                static fn (array $block): ContentBlock => new ContentBlock(
                    type: ContentBlockType::from($block['type']),
                    content: $block['content'],
                    mediaUrl: $block['mediaUrl'],
                    mediaAlt: $block['mediaAlt'],
                    mediaSource: $block['mediaSource'] ?? null,
                    linkUrl: $block['linkUrl'],
                    linkLabel: $block['linkLabel'],
                    layout: $block['layout'] ?? null,
                    imageWidthPercent: $block['imageWidthPercent'] ?? null,
                    verticalAlignment: $block['verticalAlignment'] ?? null,
                    textAlignment: $block['textAlignment'] ?? null,
                    imageFit: $block['imageFit'] ?? null,
                    embeddedPageId: $block['embeddedPageId'] ?? null,
                    eventTitle: $block['eventTitle'] ?? null,
                    eventDate: $block['eventDate'] ?? null,
                    eventTime: $block['eventTime'] ?? null,
                    eventIdentifier: $block['eventIdentifier'] ?? null,
                    eventHelpEnabled: $block['eventHelpEnabled'] ?? $block['type'] === ContentBlockType::Event->value,
                    eventHelpButtonLabel: $block['eventHelpButtonLabel'] ?? null,
                    eventActivities: array_map(
                        static fn (array $activity): EventActivityAssignment => new EventActivityAssignment($activity['activityId'], $activity['requiredHelpers']),
                        $block['eventActivities'] ?? [],
                    ),
                    eventCallToActions: array_map(
                        static fn (array $action): EventCallToAction => new EventCallToAction($action['label'], $action['url'], $action['pageId']),
                        $block['eventCallToActions'] ?? [],
                    ),
                    extensionKey: $block['extensionKey'] ?? null,
                    collectionColumns: $block['collectionColumns'] ?? null,
                    collectionItems: array_map(
                        static fn (array $item): ContentCollectionItem => new ContentCollectionItem(
                            $item['title'],
                            $item['content'],
                            $item['mediaUrl'],
                            $item['mediaAlt'],
                            $item['mediaSource'],
                        ),
                        $block['collectionItems'] ?? [],
                    ),
                ),
                $entity->getBlocks(),
            ),
            status: PageStatus::from($entity->getStatus()),
            visible: $entity->isVisible(),
            showInNavigation: $entity->isShowInNavigation(),
            navigationPosition: $entity->getNavigationPosition(),
            seoTitle: $entity->getSeoTitle(),
            seoDescription: $entity->getSeoDescription(),
            version: $entity->getVersion(),
            createdAt: $entity->getCreatedAt(),
            updatedAt: $entity->getUpdatedAt(),
            publishedAt: $entity->getPublishedAt(),
        );
    }

    public function createEntity(Page $page): PageEntity
    {
        $entity = new PageEntity(
            id: $page->id,
            title: $page->title,
            slug: $page->slug,
            navigationLabel: $page->navigationLabel,
            parentId: $page->parentId,
            status: $page->status->value,
            visible: $page->visible,
            showInNavigation: $page->showInNavigation,
            navigationPosition: $page->navigationPosition,
            seoTitle: $page->seoTitle,
            seoDescription: $page->seoDescription,
            createdAt: $page->createdAt,
            updatedAt: $page->updatedAt,
            publishedAt: $page->publishedAt,
        );
        $entity->setBlocks($this->mapBlocks($page));

        return $entity;
    }

    public function toPublishedModel(PublishedPageEntity $entity): Page
    {
        return new Page(
            id: $entity->getPage()->getId(),
            title: $entity->getTitle(),
            slug: $entity->getSlug(),
            navigationLabel: $entity->getNavigationLabel(),
            parentId: $entity->getParentId(),
            blocks: array_map(
                static fn (array $block): ContentBlock => new ContentBlock(
                    type: ContentBlockType::from($block['type']),
                    content: $block['content'],
                    mediaUrl: $block['mediaUrl'],
                    mediaAlt: $block['mediaAlt'],
                    mediaSource: $block['mediaSource'] ?? null,
                    linkUrl: $block['linkUrl'],
                    linkLabel: $block['linkLabel'],
                    layout: $block['layout'] ?? null,
                    imageWidthPercent: $block['imageWidthPercent'] ?? null,
                    verticalAlignment: $block['verticalAlignment'] ?? null,
                    textAlignment: $block['textAlignment'] ?? null,
                    imageFit: $block['imageFit'] ?? null,
                    embeddedPageId: $block['embeddedPageId'] ?? null,
                    eventTitle: $block['eventTitle'] ?? null,
                    eventDate: $block['eventDate'] ?? null,
                    eventTime: $block['eventTime'] ?? null,
                    eventIdentifier: $block['eventIdentifier'] ?? null,
                    eventHelpEnabled: $block['eventHelpEnabled'] ?? $block['type'] === ContentBlockType::Event->value,
                    eventHelpButtonLabel: $block['eventHelpButtonLabel'] ?? null,
                    eventActivities: array_map(
                        static fn (array $activity): EventActivityAssignment => new EventActivityAssignment($activity['activityId'], $activity['requiredHelpers']),
                        $block['eventActivities'] ?? [],
                    ),
                    eventCallToActions: array_map(
                        static fn (array $action): EventCallToAction => new EventCallToAction($action['label'], $action['url'], $action['pageId']),
                        $block['eventCallToActions'] ?? [],
                    ),
                    extensionKey: $block['extensionKey'] ?? null,
                    collectionColumns: $block['collectionColumns'] ?? null,
                    collectionItems: array_map(
                        static fn (array $item): ContentCollectionItem => new ContentCollectionItem(
                            $item['title'],
                            $item['content'],
                            $item['mediaUrl'],
                            $item['mediaAlt'],
                            $item['mediaSource'],
                        ),
                        $block['collectionItems'] ?? [],
                    ),
                ),
                $entity->getBlocks(),
            ),
            status: PageStatus::Published,
            visible: $entity->isVisible(),
            showInNavigation: $entity->isShowInNavigation(),
            navigationPosition: $entity->getNavigationPosition(),
            seoTitle: $entity->getSeoTitle(),
            seoDescription: $entity->getSeoDescription(),
            version: $entity->getPageVersion(),
            createdAt: $entity->getCreatedAt(),
            updatedAt: $entity->getUpdatedAt(),
            publishedAt: $entity->getPublishedAt(),
        );
    }

    public function createPublishedEntity(PageEntity $pageEntity, Page $page): PublishedPageEntity
    {
        $publishedAt = $page->publishedAt ?? throw new \LogicException('Eine Veröffentlichung benötigt einen Veröffentlichungszeitpunkt.');
        $entity = new PublishedPageEntity(
            page: $pageEntity,
            title: $page->title,
            slug: $page->slug,
            navigationLabel: $page->navigationLabel,
            parentId: $page->parentId,
            visible: $page->visible,
            showInNavigation: $page->showInNavigation,
            navigationPosition: $page->navigationPosition,
            seoTitle: $page->seoTitle,
            seoDescription: $page->seoDescription,
            pageVersion: $page->version + 1,
            createdAt: $page->createdAt,
            updatedAt: $page->updatedAt,
            publishedAt: $publishedAt,
        );
        $entity->setBlocks($this->mapBlocks($page));

        return $entity;
    }

    public function updatePublishedEntity(Page $page, PublishedPageEntity $entity): void
    {
        $publishedAt = $page->publishedAt ?? throw new \LogicException('Eine Veröffentlichung benötigt einen Veröffentlichungszeitpunkt.');
        $entity->update(
            title: $page->title,
            slug: $page->slug,
            navigationLabel: $page->navigationLabel,
            parentId: $page->parentId,
            blocks: $this->mapBlocks($page),
            visible: $page->visible,
            showInNavigation: $page->showInNavigation,
            navigationPosition: $page->navigationPosition,
            seoTitle: $page->seoTitle,
            seoDescription: $page->seoDescription,
            pageVersion: $page->version + 1,
            createdAt: $page->createdAt,
            updatedAt: $page->updatedAt,
            publishedAt: $publishedAt,
        );
    }

    public function updateEntity(Page $page, PageEntity $entity): void
    {
        $entity->update(
            title: $page->title,
            slug: $page->slug,
            navigationLabel: $page->navigationLabel,
            parentId: $page->parentId,
            status: $page->status->value,
            visible: $page->visible,
            showInNavigation: $page->showInNavigation,
            navigationPosition: $page->navigationPosition,
            seoTitle: $page->seoTitle,
            seoDescription: $page->seoDescription,
            updatedAt: $page->updatedAt,
            publishedAt: $page->publishedAt,
        );
        $entity->setBlocks($this->mapBlocks($page));
    }

    /**
     * @return list<array{
     *     type: string,
     *     content: string,
     *     mediaUrl: string|null,
     *     mediaAlt: string|null,
     *     mediaSource: string|null,
     *     linkUrl: string|null,
     *     linkLabel: string|null,
     *     layout: string|null,
     *     imageWidthPercent: int|null,
     *     verticalAlignment: string|null,
     *     textAlignment: string|null,
     *     imageFit: string|null,
     *     embeddedPageId: string|null,
     *     eventTitle: string|null,
     *     eventDate: string|null,
     *     eventTime: string|null,
     *     eventIdentifier: string|null,
     *     eventHelpEnabled: bool,
     *     eventHelpButtonLabel: string|null,
     *     eventActivities: list<array{activityId: string, requiredHelpers: int}>,
     *     eventCallToActions: list<array{label: string, url: string|null, pageId: string|null}>,
     *     extensionKey: string|null,
     *     collectionColumns: int|null,
     *     collectionItems: list<array{title: string, content: string, mediaUrl: string|null, mediaAlt: string|null, mediaSource: string|null}>
     * }>
     */
    private function mapBlocks(Page $page): array
    {
        return array_map(
            static fn (ContentBlock $block): array => [
                'type' => $block->type->value,
                'content' => $block->content,
                'mediaUrl' => $block->mediaUrl,
                'mediaAlt' => $block->mediaAlt,
                'mediaSource' => $block->mediaSource,
                'linkUrl' => $block->linkUrl,
                'linkLabel' => $block->linkLabel,
                'layout' => $block->layout,
                'imageWidthPercent' => $block->imageWidthPercent,
                'verticalAlignment' => $block->verticalAlignment,
                'textAlignment' => $block->textAlignment,
                'imageFit' => $block->imageFit,
                'embeddedPageId' => $block->embeddedPageId,
                'eventTitle' => $block->eventTitle,
                'eventDate' => $block->eventDate,
                'eventTime' => $block->eventTime,
                'eventIdentifier' => $block->eventIdentifier,
                'eventHelpEnabled' => $block->eventHelpEnabled,
                'eventHelpButtonLabel' => $block->eventHelpButtonLabel,
                'eventActivities' => array_map(
                    static fn (EventActivityAssignment $activity): array => ['activityId' => $activity->activityId, 'requiredHelpers' => $activity->requiredHelpers],
                    $block->eventActivities,
                ),
                'eventCallToActions' => array_map(
                    static fn (EventCallToAction $action): array => ['label' => $action->label, 'url' => $action->url, 'pageId' => $action->pageId],
                    $block->eventCallToActions,
                ),
                'extensionKey' => $block->extensionKey,
                'collectionColumns' => $block->collectionColumns,
                'collectionItems' => array_map(
                    static fn (ContentCollectionItem $item): array => [
                        'title' => $item->title,
                        'content' => $item->content,
                        'mediaUrl' => $item->mediaUrl,
                        'mediaAlt' => $item->mediaAlt,
                        'mediaSource' => $item->mediaSource,
                    ],
                    $block->collectionItems,
                ),
            ],
            $page->blocks,
        );
    }
}
