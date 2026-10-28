<?php

namespace App\Data\Media\Library\Adapter;

use App\Logic\Content\Page\Dto\PageMediaReferenceResponse;
use App\Logic\Content\Page\Query\ListPageMediaReferencesQuery;
use App\Logic\Content\Page\UseCase\ReplacePageMediaReferenceUseCase;
use App\Logic\Media\Library\MediaUsageSourceInterface;
use App\Logic\Media\Library\Model\MediaUsage;
use App\Logic\Media\Library\Model\MediaUsageArea;

/** Adapter auf die Bildverweise des Seitenmoduls (Entwurf und veröffentlichte Fassung). */
readonly class ContentPageMediaUsageSource implements MediaUsageSourceInterface
{
    public function __construct(
        private ListPageMediaReferencesQuery $query,
        private ReplacePageMediaReferenceUseCase $replaceUseCase,
    ) {
    }

    public function findUsages(): array
    {
        return array_map(
            static fn (PageMediaReferenceResponse $reference): MediaUsage => new MediaUsage(
                url: $reference->mediaUrl,
                area: MediaUsageArea::Page,
                referenceId: $reference->pageId,
                title: $reference->pageTitle,
                detail: match (true) {
                    $reference->inDraft && $reference->inPublication => 'Entwurf und veröffentlichte Fassung',
                    $reference->inDraft => 'nur im Entwurf',
                    default => 'nur in der veröffentlichten Fassung',
                },
            ),
            $this->query->execute(),
        );
    }

    public function replaceReferences(string $url, ?string $replacementUrl): int
    {
        return $this->replaceUseCase->execute($url, $replacementUrl);
    }
}
