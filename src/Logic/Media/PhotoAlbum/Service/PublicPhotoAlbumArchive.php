<?php

namespace App\Logic\Media\PhotoAlbum\Service;

use App\Logic\Media\PhotoAlbum\Dto\PhotoAlbumYearResponse;
use App\Logic\Media\PhotoAlbum\Dto\PublicPhotoAlbumActionResponse;
use App\Logic\Media\PhotoAlbum\Dto\PublicPhotoAlbumResponse;
use App\Logic\Media\PhotoAlbum\Manager\PhotoAlbumManagerInterface;
use App\Logic\Media\PhotoAlbum\Model\PhotoAlbum;
use App\Logic\Media\PhotoAlbum\PhotoAlbumPageDirectoryInterface;

/**
 * Öffentliches Fotoarchiv: sichtbare Einträge nach Jahren gruppiert. Die Einträge kommen aus dem
 * Cache des Managers; verlinkte Seiten werden bei jeder Anfrage aufgelöst, damit eine geänderte
 * Seitenadresse ohne Kopplung an das Seitenmodul sofort stimmt.
 */
readonly class PublicPhotoAlbumArchive
{
    public function __construct(
        private PhotoAlbumManagerInterface $manager,
        private PhotoAlbumPageDirectoryInterface $pages,
    ) {
    }

    /**
     * @return list<PhotoAlbumYearResponse> neueste zuerst; Jahre ohne sichtbare Einträge fehlen
     */
    public function years(): array
    {
        $years = [];
        foreach ($this->visibleAlbumsByYear() as $year => $albums) {
            $years[] = new PhotoAlbumYearResponse($year, $this->publicAlbums($albums));
        }

        return $years;
    }

    public function get(int $year): PhotoAlbumYearResponse
    {
        return new PhotoAlbumYearResponse($year, $this->publicAlbums($this->visibleAlbumsByYear()[$year] ?? []));
    }

    /**
     * @return array<int, list<PhotoAlbum>> neueste Jahre zuerst
     */
    private function visibleAlbumsByYear(): array
    {
        $albumsByYear = [];
        foreach ($this->manager->all() as $album) {
            if ($album->visible) {
                $albumsByYear[(int) $album->date->format('Y')][] = $album;
            }
        }
        krsort($albumsByYear);

        return $albumsByYear;
    }

    /**
     * @param list<PhotoAlbum> $albums
     * @return list<PublicPhotoAlbumResponse>
     */
    private function publicAlbums(array $albums): array
    {
        $pageSlugs = [];
        $publicAlbums = [];
        foreach ($albums as $album) {
            $actions = [];
            foreach ($album->actions as $action) {
                $pageSlug = null;
                if ($action->pageId !== null) {
                    if (!array_key_exists($action->pageId, $pageSlugs)) {
                        $pageSlugs[$action->pageId] = $this->pages->publishedSlug($action->pageId);
                    }
                    $pageSlug = $pageSlugs[$action->pageId];
                }
                $actions[] = new PublicPhotoAlbumActionResponse($action->label, $action->url, $action->pageId, $pageSlug);
            }
            $publicAlbums[] = new PublicPhotoAlbumResponse($album->id, $album->title, $album->date, $actions);
        }

        return $publicAlbums;
    }
}
