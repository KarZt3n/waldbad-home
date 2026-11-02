<?php

namespace App\UI\Media\PhotoAlbum\Http;

use App\Logic\Media\PhotoAlbum\Dto\PhotoAlbumResponse;
use App\Logic\Media\PhotoAlbum\Dto\PhotoAlbumYearResponse;
use App\Logic\Media\PhotoAlbum\Dto\PublicPhotoAlbumActionResponse;
use App\Logic\Media\PhotoAlbum\Dto\PublicPhotoAlbumResponse;
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

    /**
     * @param list<PhotoAlbumYearResponse> $years
     * @return array{items: list<array{year: int, count: int}>}
     */
    public function years(array $years): array
    {
        return ['items' => array_map(
            static fn (PhotoAlbumYearResponse $year): array => ['year' => $year->year, 'count' => count($year->albums)],
            $years,
        )];
    }

    /**
     * @return array{
     *     year: int,
     *     items: list<array{
     *         id: string,
     *         title: string,
     *         date: string,
     *         actions: list<array{label: string, url: string|null, pageId: string|null, pageSlug: string|null}>
     *     }>
     * }
     */
    public function year(PhotoAlbumYearResponse $year): array
    {
        return [
            'year' => $year->year,
            'items' => array_map(static fn (PublicPhotoAlbumResponse $album): array => [
                'id' => $album->id,
                'title' => $album->title,
                'date' => $album->date->format('Y-m-d'),
                'actions' => array_map(
                    static fn (PublicPhotoAlbumActionResponse $action): array => [
                        'label' => $action->label,
                        'url' => $action->url,
                        'pageId' => $action->pageId,
                        'pageSlug' => $action->pageSlug,
                    ],
                    $album->actions,
                ),
            ], $year->albums),
        ];
    }
}
