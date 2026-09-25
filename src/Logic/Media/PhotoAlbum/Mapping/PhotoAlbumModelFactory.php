<?php

namespace App\Logic\Media\PhotoAlbum\Mapping;

use App\Logic\Common\IdentifierGeneratorInterface;
use App\Logic\Media\PhotoAlbum\Dto\PhotoAlbumActionInput;
use App\Logic\Media\PhotoAlbum\Model\PhotoAlbumAction;

readonly class PhotoAlbumModelFactory
{
    public function __construct(private IdentifierGeneratorInterface $identifierGenerator)
    {
    }

    /**
     * Aktionsbuttons erhalten bei jedem Speichern neue Kennungen, da sie vollständig ersetzt werden.
     *
     * @param list<PhotoAlbumActionInput> $inputs
     * @return list<PhotoAlbumAction>
     */
    public function actions(array $inputs): array
    {
        return array_map(
            fn (PhotoAlbumActionInput $input, int $position): PhotoAlbumAction => new PhotoAlbumAction(
                $this->identifierGenerator->generate(),
                $position,
                $input->label,
                $input->url,
                $input->pageId,
            ),
            $inputs,
            array_keys($inputs),
        );
    }
}
