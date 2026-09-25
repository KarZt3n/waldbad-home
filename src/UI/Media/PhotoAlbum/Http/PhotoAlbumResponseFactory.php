<?php

namespace App\UI\Media\PhotoAlbum\Http;

use App\Logic\Media\PhotoAlbum\Dto\PhotoAlbumResponse;
use App\Logic\Media\PhotoAlbum\Model\PhotoAlbumAction;

readonly class PhotoAlbumResponseFactory
{
    /**
     * @return array{
     *     id: string,
     *     title: string,
     *     date: string,
     *     year: int,
     *     visible: bool,
     *     actions: list<array{label: string, url: string|null, pageId: string|null}>,
     *     createdAt: string,
     *     updatedAt: string
     * }
     */
    public function album(PhotoAlbumResponse $album): array
    {
        return [
            'id' => $album->id,
            'title' => $album->title,
            'date' => $album->date->format('Y-m-d'),
            'year' => (int) $album->date->format('Y'),
            'visible' => $album->visible,
            'actions' => array_map(
                static fn (PhotoAlbumAction $action): array => ['label' => $action->label, 'url' => $action->url, 'pageId' => $action->pageId],
                $album->actions,
            ),
            'createdAt' => $album->createdAt->format(\DateTimeInterface::ATOM),
            'updatedAt' => $album->updatedAt->format(\DateTimeInterface::ATOM),
        ];
    }

    /**
     * @param list<PhotoAlbumResponse> $albums
     * @return array{items: list<array<string, mixed>>, total: int}
     */
    public function collection(array $albums): array
    {
        return ['items' => array_map($this->album(...), $albums), 'total' => count($albums)];
    }
}
