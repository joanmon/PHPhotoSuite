<?php

namespace PHPhotoSuit\PhotoSuite\Infrastructure\Presenter;

use PHPhotoSuit\PhotoSuite\Domain\Model\CollectionOfThumbCollection;
use PHPhotoSuit\PhotoSuite\Domain\Model\Photo;
use PHPhotoSuit\PhotoSuite\Domain\Model\PhotoCollection;
use PHPhotoSuit\PhotoSuite\Domain\Model\PhotoPresenter;
use PHPhotoSuit\PhotoSuite\Domain\Model\PhotoThumb;
use PHPhotoSuit\PhotoSuite\Domain\Model\PhotoThumbCollection;
use PHPhotoSuit\PhotoSuite\Domain\Model\PhotoThumbPresenter;

class ArrayThumbPresenter implements PhotoThumbPresenter
{
    /** @var PhotoPresenter */
    private $arrayPhotoPresenter;

    /**
     * @param PhotoPresenter $arrayPhotoPresenter
     */
    public function __construct(PhotoPresenter $arrayPhotoPresenter)
    {
        $this->arrayPhotoPresenter = $arrayPhotoPresenter;
    }

    /**
     * @param Photo $photo
     * @param PhotoThumbCollection $thumbCollection
     * @return mixed
     */
    public function write(Photo $photo, PhotoThumbCollection $thumbCollection)
    {
        $photoArray = $this->arrayPhotoPresenter->write($photo);
        $photoArray['thumbs'] = $this->presentArrayOfThumbs($thumbCollection);

        return $photoArray;
    }

    /**
     * @param $thumbCollection
     * @return array
     */
    private function presentArrayOfThumbs($thumbCollection)
    {
        $thumbs = [];
        /** @var PhotoThumb $thumb */
        foreach ($thumbCollection as $thumb) {
            $thumbs[$thumb->width() . 'x' . $thumb->height()] = [
                'id' => $thumb->id(),
                'url' => $thumb->photoThumbHttpUrl(),
                'height' => $thumb->height(),
                'width' => $thumb->width(),
                'file' => is_null($thumb->photoThumbFile()) ? '' : $thumb->photoThumbFile()->filePath()
            ];
        }
        return $thumbs;
    }

    /**
     * @param PhotoCollection $photoCollection
     * @param CollectionOfThumbCollection $collectionOfThumbCollection
     * @return mixed
     */
    public function writeCollection(
        PhotoCollection $photoCollection,
        CollectionOfThumbCollection $collectionOfThumbCollection
    ) {
        $response = [];
        foreach ($photoCollection as $key => $photo) {
            $response[] = $this->write($photo, $collectionOfThumbCollection[$key]);
        }
        return $response;
    }
}
