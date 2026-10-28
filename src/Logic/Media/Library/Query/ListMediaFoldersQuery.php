<?php

namespace App\Logic\Media\Library\Query;

use App\Logic\Media\Library\MediaLibraryStorageInterface;
use App\Logic\Media\Library\Model\MediaFolder;

readonly class ListMediaFoldersQuery
{
    public function __construct(private MediaLibraryStorageInterface $storage)
    {
    }

    /**
     * @return list<MediaFolder> nach Pfad sortiert
     */
    public function execute(): array
    {
        $folders = $this->storage->folders();
        usort($folders, static fn (MediaFolder $left, MediaFolder $right): int => strcmp($left->path, $right->path));

        return $folders;
    }
}
