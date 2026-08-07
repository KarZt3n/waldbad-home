<?php

namespace App\Logic\Content\Page\Model;

use App\Logic\Common\Exception\BusinessRuleViolationException;

readonly class Page
{
    /**
     * @param list<ContentBlock> $blocks
     */
    public function __construct(
        public string $id,
        public string $title,
        public string $slug,
        public string $navigationLabel,
        public ?string $parentId,
        public array $blocks,
        public PageStatus $status,
        public bool $visible,
        public bool $showInNavigation,
        public int $navigationPosition,
        public ?string $seoTitle,
        public ?string $seoDescription,
        public int $version,
        public \DateTimeImmutable $createdAt,
        public \DateTimeImmutable $updatedAt,
        public ?\DateTimeImmutable $publishedAt,
    ) {
        if (trim($this->title) === '' || trim($this->navigationLabel) === '') {
            throw new BusinessRuleViolationException('Seitentitel und Navigationsbezeichnung dürfen nicht leer sein.');
        }

        if (preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $this->slug) !== 1) {
            throw new BusinessRuleViolationException('Der Slug darf nur Kleinbuchstaben, Zahlen und Bindestriche enthalten.');
        }

        if ($this->navigationPosition < 0) {
            throw new BusinessRuleViolationException('Die Navigationsposition darf nicht negativ sein.');
        }
    }

    /**
     * @param list<ContentBlock> $blocks
     */
    public function revise(
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
        \DateTimeImmutable $updatedAt,
    ): self {
        if ($this->status === PageStatus::Archived) {
            throw new BusinessRuleViolationException('Eine archivierte Seite kann nicht bearbeitet werden.');
        }

        return new self(
            id: $this->id,
            title: $title,
            slug: $slug,
            navigationLabel: $navigationLabel,
            parentId: $parentId,
            blocks: $blocks,
            status: PageStatus::Draft,
            visible: $visible,
            showInNavigation: $showInNavigation,
            navigationPosition: $navigationPosition,
            seoTitle: $seoTitle,
            seoDescription: $seoDescription,
            version: $this->version,
            createdAt: $this->createdAt,
            updatedAt: $updatedAt,
            publishedAt: $this->publishedAt,
        );
    }

    public function requestReview(\DateTimeImmutable $updatedAt): self
    {
        if ($this->status !== PageStatus::Draft) {
            throw new BusinessRuleViolationException('Nur ein Entwurf kann zur Prüfung eingereicht werden.');
        }

        return $this->withStatus(PageStatus::InReview, $updatedAt, $this->publishedAt);
    }

    public function publish(\DateTimeImmutable $publishedAt): self
    {
        if ($this->blocks === []) {
            throw new BusinessRuleViolationException('Eine leere Seite kann nicht veröffentlicht werden.');
        }

        if ($this->status === PageStatus::Archived) {
            throw new BusinessRuleViolationException('Eine archivierte Seite kann nicht veröffentlicht werden.');
        }

        return $this->withStatus(PageStatus::Published, $publishedAt, $publishedAt);
    }

    public function unpublish(\DateTimeImmutable $updatedAt): self
    {
        if ($this->publishedAt === null || $this->status === PageStatus::Archived) {
            throw new BusinessRuleViolationException('Nur eine aktuell im Frontend veröffentlichte Seite kann zurückgezogen werden.');
        }

        return $this->withStatus(PageStatus::Draft, $updatedAt, null);
    }

    public function archive(\DateTimeImmutable $updatedAt): self
    {
        return $this->withStatus(PageStatus::Archived, $updatedAt, $this->publishedAt);
    }

    public function reposition(int $navigationPosition, \DateTimeImmutable $updatedAt): self
    {
        return new self(
            id: $this->id,
            title: $this->title,
            slug: $this->slug,
            navigationLabel: $this->navigationLabel,
            parentId: $this->parentId,
            blocks: $this->blocks,
            status: $this->status,
            visible: $this->visible,
            showInNavigation: $this->showInNavigation,
            navigationPosition: $navigationPosition,
            seoTitle: $this->seoTitle,
            seoDescription: $this->seoDescription,
            version: $this->version,
            createdAt: $this->createdAt,
            updatedAt: $updatedAt,
            publishedAt: $this->publishedAt,
        );
    }

    private function withStatus(
        PageStatus $status,
        \DateTimeImmutable $updatedAt,
        ?\DateTimeImmutable $publishedAt,
    ): self {
        return new self(
            id: $this->id,
            title: $this->title,
            slug: $this->slug,
            navigationLabel: $this->navigationLabel,
            parentId: $this->parentId,
            blocks: $this->blocks,
            status: $status,
            visible: $this->visible,
            showInNavigation: $this->showInNavigation,
            navigationPosition: $this->navigationPosition,
            seoTitle: $this->seoTitle,
            seoDescription: $this->seoDescription,
            version: $this->version,
            createdAt: $this->createdAt,
            updatedAt: $updatedAt,
            publishedAt: $publishedAt,
        );
    }
}
