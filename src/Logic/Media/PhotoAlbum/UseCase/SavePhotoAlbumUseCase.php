<?php

namespace App\Logic\Media\PhotoAlbum\UseCase;

use App\Logic\Common\ClockInterface;
use App\Logic\Common\IdentifierGeneratorInterface;
use App\Logic\Media\PhotoAlbum\Dto\PhotoAlbumResponse;
use App\Logic\Media\PhotoAlbum\Dto\SavePhotoAlbumRequest;
use App\Logic\Media\PhotoAlbum\Manager\PhotoAlbumManagerInterface;
use App\Logic\Media\PhotoAlbum\Mapping\PhotoAlbumModelFactory;
use App\Logic\Media\PhotoAlbum\Model\PhotoAlbum;

readonly class SavePhotoAlbumUseCase
{
    public function __construct(
        private PhotoAlbumManagerInterface $manager,
        private PhotoAlbumModelFactory $factory,
        private IdentifierGeneratorInterface $identifierGenerator,
        private ClockInterface $clock,
    ) {
    }

    public function execute(SavePhotoAlbumRequest $request): PhotoAlbumResponse
    {
        $now = $this->clock->now();
        $actions = $this->factory->actions($request->actions);
        $album = $request->id === null
            ? new PhotoAlbum($this->identifierGenerator->generate(), trim($request->title), $request->date, $request->visible, $actions, $now, $now)
            : $this->manager->get($request->id)->revise(trim($request->title), $request->date, $request->visible, $actions, $now);

        return PhotoAlbumResponse::fromAlbum($this->manager->save($album));
    }
}
