<?php

namespace App\Logic\Media\PhotoAlbum;

/** Adapter zum Seitenmodul: Aktionsbuttons eines Foto-Eintrags können auf eine CMS-Seite verweisen. */
interface PhotoAlbumPageDirectoryInterface
{
    /** Slug der veröffentlichten Seite oder `null`, wenn sie nicht (mehr) veröffentlicht ist. */
    public function publishedSlug(string $pageId): ?string;
}
