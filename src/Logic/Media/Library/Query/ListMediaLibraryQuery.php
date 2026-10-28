<?php

namespace App\Logic\Media\Library\Query;

use App\Logic\Media\Library\Dto\ListMediaLibraryRequest;
use App\Logic\Media\Library\Dto\MediaLibraryItemResponse;
use App\Logic\Media\Library\Exception\MediaFolderNotFoundException;
use App\Logic\Media\Library\MediaLibraryStorageInterface;
use App\Logic\Media\Library\MediaUsageSourceInterface;
use App\Logic\Media\Library\Model\MediaFile;

readonly class ListMediaLibraryQuery
{
    public function __construct(
        private MediaLibraryStorageInterface $storage,
        private MediaUsageSourceInterface $usageSource,
    ) {
    }

    /**
     * @return list<MediaLibraryItemResponse>
     */
    public function execute(ListMediaLibraryRequest $request): array
    {
        if (!$this->storage->folderExists($request->folderPath)) {
            throw new MediaFolderNotFoundException($request->folderPath);
        }

        $usagesByUrl = [];
        foreach ($this->usageSource->findUsages() as $usage) {
            $usagesByUrl[$usage->url][] = $usage;
        }

        return array_map(
            static fn (MediaFile $file): MediaLibraryItemResponse => new MediaLibraryItemResponse(
                file: $file,
                usages: $usagesByUrl[$file->url] ?? [],
            ),
            $this->storage->files($request->folderPath, $request->recursive),
        );
    }
}
