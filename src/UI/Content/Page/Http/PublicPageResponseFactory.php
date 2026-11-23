<?php

namespace App\UI\Content\Page\Http;

use App\Logic\Content\Page\Dto\PageResponse;
use App\Logic\Content\Page\Model\ContentBlock;
use App\Logic\Content\Page\Query\ListReferencedPublishedPagesQuery;
use App\Logic\Media\Image\Model\ImageDimensions;
use App\Logic\Media\Image\Query\GetImageDimensionsQuery;
use App\UI\Common\Http\ApiResponseFactory;

/**
 * Öffentliche Seitendaten für `GET /api/public/v1/pages/{slug}` und die in die HTML-Seite
 * eingebettete Erstausgabe: die Seite selbst, alle beim Anzeigen gebrauchten weiteren Seiten
 * (`referencedPages`) und die Maße ihrer Bilder (`imageSizes`). So kommen eingebettete Seiten ohne
 * eigene Anfrage, und Bilder bekommen vorab ihren Platz — beides verhindert, dass die Seite beim
 * Laden springt.
 */
readonly class PublicPageResponseFactory
{
    public function __construct(
        private ApiResponseFactory $responseFactory,
        private ListReferencedPublishedPagesQuery $referencedPages,
        private GetImageDimensionsQuery $imageDimensions,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function pageData(PageResponse $page): array
    {
        $referencedPages = $this->referencedPages->execute($page->id);
        $imageUrls = [];
        foreach ([$page, ...$referencedPages] as $shownPage) {
            foreach ($shownPage->blocks as $block) {
                array_push($imageUrls, ...$this->imageUrls($block));
            }
        }

        return [
            ...$this->responseFactory->pageData($page),
            'referencedPages' => array_map($this->responseFactory->pageData(...), $referencedPages),
            'imageSizes' => (object) array_map(
                static fn (ImageDimensions $dimensions): array => ['width' => $dimensions->width, 'height' => $dimensions->height],
                $this->imageDimensions->execute($imageUrls),
            ),
        ];
    }

    /** @return list<string> */
    private function imageUrls(ContentBlock $block): array
    {
        $urls = $block->mediaUrl === null ? [] : [$block->mediaUrl];
        foreach ($block->collectionItems as $item) {
            if ($item->mediaUrl !== null) {
                $urls[] = $item->mediaUrl;
            }
        }

        return $urls;
    }
}
