<?php

namespace App\Data\Content\Page\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'cms_page_publication')]
#[ORM\UniqueConstraint(name: 'uniq_cms_page_publication_slug', columns: ['slug'])]
#[ORM\Index(name: 'idx_cms_page_publication_navigation', columns: ['visible', 'show_in_navigation', 'navigation_position'])]
class PublishedPageEntity
{
    /**
     * @var list<array{
     *     type: string,
     *     content: string,
     *     mediaUrl: string|null,
     *     mediaAlt: string|null,
     *     linkUrl: string|null,
     *     linkLabel: string|null,
     *     layout?: string|null,
     *     imageWidthPercent?: int|null,
     *     verticalAlignment?: string|null,
     *     textAlignment?: string|null,
     *     imageFit?: string|null,
     *     embeddedPageId?: string|null,
     *     eventTitle?: string|null,
     *     eventDate?: string|null,
     *     eventTime?: string|null,
     *     eventIdentifier?: string|null,
     *     eventHelpEnabled?: bool,
     *     eventHelpButtonLabel?: string|null,
     *     extensionKey?: string|null
     * }>
     */
    #[ORM\Column(type: Types::JSON)]
    private array $blocks = [];

    public function __construct(
        #[ORM\Id]
        #[ORM\OneToOne(targetEntity: PageEntity::class)]
        #[ORM\JoinColumn(name: 'page_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
        private PageEntity $page,
        #[ORM\Column(type: Types::STRING, length: 180)]
        private string $title,
        #[ORM\Column(type: Types::STRING, length: 180)]
        private string $slug,
        #[ORM\Column(type: Types::STRING, length: 180)]
        private string $navigationLabel,
        #[ORM\Column(type: Types::STRING, length: 36, nullable: true)]
        private ?string $parentId,
        #[ORM\Column(type: Types::BOOLEAN)]
        private bool $visible,
        #[ORM\Column(type: Types::BOOLEAN)]
        private bool $showInNavigation,
        #[ORM\Column(type: Types::INTEGER)]
        private int $navigationPosition,
        #[ORM\Column(type: Types::STRING, length: 180, nullable: true)]
        private ?string $seoTitle,
        #[ORM\Column(type: Types::TEXT, nullable: true)]
        private ?string $seoDescription,
        #[ORM\Column(type: Types::INTEGER)]
        private int $pageVersion,
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
        private \DateTimeImmutable $createdAt,
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
        private \DateTimeImmutable $updatedAt,
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
        private \DateTimeImmutable $publishedAt,
    ) {
    }

    public function getPage(): PageEntity
    {
        return $this->page;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function getNavigationLabel(): string
    {
        return $this->navigationLabel;
    }

    public function getParentId(): ?string
    {
        return $this->parentId;
    }

    /**
     * @return list<array{
     *     type: string,
     *     content: string,
     *     mediaUrl: string|null,
     *     mediaAlt: string|null,
     *     linkUrl: string|null,
     *     linkLabel: string|null,
     *     layout?: string|null,
     *     imageWidthPercent?: int|null,
     *     verticalAlignment?: string|null,
     *     textAlignment?: string|null,
     *     imageFit?: string|null,
     *     embeddedPageId?: string|null,
     *     eventTitle?: string|null,
     *     eventDate?: string|null,
     *     eventTime?: string|null,
     *     eventIdentifier?: string|null,
     *     eventHelpEnabled?: bool,
     *     eventHelpButtonLabel?: string|null,
     *     extensionKey?: string|null
     * }>
     */
    public function getBlocks(): array
    {
        return $this->blocks;
    }

    public function isVisible(): bool
    {
        return $this->visible;
    }

    public function isShowInNavigation(): bool
    {
        return $this->showInNavigation;
    }

    public function getNavigationPosition(): int
    {
        return $this->navigationPosition;
    }

    public function getSeoTitle(): ?string
    {
        return $this->seoTitle;
    }

    public function getSeoDescription(): ?string
    {
        return $this->seoDescription;
    }

    public function getPageVersion(): int
    {
        return $this->pageVersion;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getPublishedAt(): \DateTimeImmutable
    {
        return $this->publishedAt;
    }

    /**
     * @param list<array{
     *     type: string,
     *     content: string,
     *     mediaUrl: string|null,
     *     mediaAlt: string|null,
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
     *     extensionKey: string|null
     * }> $blocks
     */
    public function update(
        string $title,
        string $slug,
        string $navigationLabel,
        ?string $parentId,
        array $blocks,
        bool $visible,
        bool $showInNavigation,
        int $navigationPosition,
        ?string $seoTitle,
        ?string $seoDescription,
        int $pageVersion,
        \DateTimeImmutable $createdAt,
        \DateTimeImmutable $updatedAt,
        \DateTimeImmutable $publishedAt,
    ): void {
        $this->title = $title;
        $this->slug = $slug;
        $this->navigationLabel = $navigationLabel;
        $this->parentId = $parentId;
        $this->blocks = $blocks;
        $this->visible = $visible;
        $this->showInNavigation = $showInNavigation;
        $this->navigationPosition = $navigationPosition;
        $this->seoTitle = $seoTitle;
        $this->seoDescription = $seoDescription;
        $this->pageVersion = $pageVersion;
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
        $this->publishedAt = $publishedAt;
    }

    /**
     * @param list<array{
     *     type: string,
     *     content: string,
     *     mediaUrl: string|null,
     *     mediaAlt: string|null,
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
     *     extensionKey: string|null
     * }> $blocks
     */
    public function setBlocks(array $blocks): void
    {
        $this->blocks = $blocks;
    }
}
