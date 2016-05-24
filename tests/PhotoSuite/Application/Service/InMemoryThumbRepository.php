<?php

namespace PHPhotoSuit\Tests\PhotoSuite\Application\Service;

use PHPhotoSuit\PhotoSuite\Domain\HttpUrl;
use PHPhotoSuit\PhotoSuite\Domain\Model\PhotoId;
use PHPhotoSuit\PhotoSuite\Domain\Model\PhotoThumb;
use PHPhotoSuit\PhotoSuite\Domain\Model\PhotoThumbRepository;
use PHPhotoSuit\PhotoSuite\Domain\Model\PhotoThumbSize;
use PHPhotoSuit\PhotoSuite\Domain\Model\ThumbId;

class InMemoryThumbRepository implements PhotoThumbRepository
{
    /** @var PhotoThumb[] */
    private $thumbs;

    /**
     * This method should be called once to create the schema of persistence system
     * @return void
     */
    public function initialize()
    {
    }

    public function __construct()
    {
        $this->thumbs = [
            new PhotoThumb(
                new ThumbId(),
                new PhotoId(),
                new HttpUrl('http://test'),
                new PhotoThumbSize(1,1)
            )
        ];
    }

    /**
     * @param PhotoId $photoId
     * @param PhotoThumbSize $thumbSize
     * @return PhotoThumb | null
     */
    public function findOneBy(PhotoId $photoId, PhotoThumbSize $thumbSize)
    {
        foreach ($this->thumbs as $thumb) {
            if ($photoId->id() === $thumb->id() &&
                $thumbSize->height() === $thumb->height() &&
                $thumbSize->width() === $thumb->width()) {

                return $thumb;
            }
        }
    }

    /**
     * @param PhotoThumb $thumb
     * @return void
     */
    public function save(PhotoThumb $thumb)
    {
        $this->thumbs[] = $thumb;
    }

    /**
     * @param ThumbId $thumbId
     * @return ThumbId
     */
    public function ensureUniqueThumbId(ThumbId $thumbId = null)
    {
        return new ThumbId();
    }
}