<?php

namespace PHPhotoSuit\PhotoSuite\Infrastructure\Presenter;

use PHPhotoSuit\PhotoSuite\Domain\Model\Photo;
use PHPhotoSuit\PhotoSuite\Domain\Model\PhotoCollection;
use PHPhotoSuit\PhotoSuite\Domain\Model\PhotoPresenter;

class ArrayPhotoPresenter implements PhotoPresenter
{

    /**
     * @param Photo $photo
     * @return array
     */
    public function write(Photo $photo)
    {
        return [
            'id' => $photo->id(),
            'resourceId' => $photo->resourceId(),
            'name' => $photo->name(),
            'url' => $photo->getPhotoHttpUrl(),
            'file' => is_null($photo->photoFile()) ? '' : $photo->photoFile()->filePath()
        ];
    }

    /**
     * @param PhotoCollection $collection
     * @return array
     */
    public function writeCollection(PhotoCollection $collection)
    {
        return array_map([ArrayPhotoPresenter::class, 'write'], $collection->toArray());
    }
}
