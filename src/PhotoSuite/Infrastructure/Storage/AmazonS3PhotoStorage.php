<?php

namespace PHPhotoSuit\PhotoSuite\Infrastructure\Storage;

use Aws\S3\S3Client;
use PHPhotoSuit\PhotoSuite\Domain\HttpUrl;
use PHPhotoSuit\PhotoSuite\Domain\Model\Photo;
use PHPhotoSuit\PhotoSuite\Domain\Model\PhotoFile;
use PHPhotoSuit\PhotoSuite\Domain\Model\PhotoFormat;
use PHPhotoSuit\PhotoSuite\Domain\Model\PhotoId;
use PHPhotoSuit\PhotoSuite\Domain\Model\PhotoName;
use PHPhotoSuit\PhotoSuite\Domain\Model\PhotoStorage;
use PHPhotoSuit\PhotoSuite\Domain\Model\PhotoThumb;
use PHPhotoSuit\PhotoSuite\Domain\Model\PhotoThumbSize;
use PHPhotoSuit\PhotoSuite\Domain\ResourceId;
use PHPhotoSuit\PhotoSuite\Infrastructure\ThumbGenerator\ThumbGeneratorConfig;

class AmazonS3PhotoStorage implements PhotoStorage
{
    /** @var S3Client */
    private $s3;
    /** @var AmazonS3Config */
    private $config;
    /** @var ThumbGeneratorConfig */
    private $thumbConfig;

    /**
     * AmazonS3PhotoStorage constructor.
     * @param AmazonS3Config $config
     * @param ThumbGeneratorConfig $thumbConfig
     */
    public function __construct(AmazonS3Config $config, ThumbGeneratorConfig $thumbConfig)
    {
        $this->config = $config;
        $this->thumbConfig = $thumbConfig;
        $this->s3 = new S3Client([
            'version' => 'latest',
            'region'  => 'us-west-2',
            'credentials' => [
                'key'    => $this->config->getKey(),
                'secret' => $this->config->getSecret()
            ]
        ]);
    }


    /**
     * @param Photo $photo
     * @return PhotoFile | null
     */
    public function upload(Photo $photo)
    {
        $this->s3->putObject([
            'Bucket'        => $this->config->bucket(),
            'Key'           => $this->getPhotoUri($photo->id(), $photo->slug(), $photo->photoFile()->format()),
            'Body'          => fopen($photo->photoFile()->filePath(), 'r'),
            'ContentType'   => $photo->photoFile()->mimeType(),
            'ACL'           => 'public-read',
        ]);
        rename($photo->photoFile()->filePath(), $this->thumbConfig->tempPath() . '/' . md5($photo->getPhotoHttpUrl()));
    }

    /**
     * @param Photo $photo
     * @return boolean
     */
    public function remove(Photo $photo)
    {
        $itemsUri = explode('/', $photo->getPhotoHttpUrl());
        $response = $this->s3->deleteObject([
            'Bucket' => $this->config->bucket(),
            'Key'    => trim(array_pop($itemsUri))
        ]);
        return !is_null($response->get('RequestCharged'));
    }

    /**
     * @param PhotoThumb $thumb
     * @return boolean
     */
    public function removeThumb(PhotoThumb $thumb)
    {
        $itemsUri = explode('/', $thumb->photoThumbHttpUrl());
        $response = $this->s3->deleteObject([
            'Bucket' => $this->config->bucket(),
            'Key'    => trim(array_pop($itemsUri))
        ]);
        return !is_null($response->get('RequestCharged'));
    }

    /**
     * @param PhotoId $photoId
     * @param ResourceId $resourceId
     * @param PhotoName $photoName
     * @param PhotoFile $photoFile
     * @return HttpUrl
     */
    public function getPhotoHttpUrlBy(
        PhotoId $photoId,
        ResourceId $resourceId,
        PhotoName $photoName,
        PhotoFile $photoFile
    ) {
        return new HttpUrl($this->config->urlBase() . '/' .
        $this->getPhotoUri($photoId->id(), $photoName->slug(), $photoFile->format()));
    }

    /**
     * @param PhotoThumb $thumb
     * @param Photo $photo
     * @return null|PhotoFile
     */
    public function uploadThumb(PhotoThumb $thumb, Photo $photo)
    {
        $this->s3->putObject([
            'Bucket'        => $this->config->bucket(),
            'Key'           => $this->getThumbUri(
                $photo->id(),
                $photo->slug(),
                $thumb->width(),
                $thumb->height(),
                $thumb->photoThumbFile()->format()
            ),
            'Body'          => fopen($thumb->photoThumbFile()->filePath(), 'r'),
            'ContentType'   => $thumb->photoThumbFile()->mimeType(),
            'ACL'           => 'public-read',
        ]);
    }

    /**
     * @param PhotoId $photoId
     * @param ResourceId $resourceId
     * @param PhotoName $photoName
     * @param PhotoThumbSize $photoThumbSize
     * @param PhotoFormat $photoFormat
     * @return HttpUrl
     */
    public function getPhotoThumbHttpUrlBy(
        PhotoId $photoId,
        ResourceId $resourceId,
        PhotoName $photoName,
        PhotoThumbSize $photoThumbSize,
        PhotoFormat $photoFormat
    ) {
        return new HttpUrl($this->config->urlBase() . '/' .
            $this->getThumbUri(
                $photoId->id(),
                $photoName->slug(),
                $photoThumbSize->width(),
                $photoThumbSize->height(),
                $photoFormat->value()
            ));
    }

    /**
     * @param string $uniqueId
     * @param string $nameSlug
     * @param string $format
     * @return string
     */
    private function getPhotoUri($uniqueId, $nameSlug, $format)
    {
        return  sprintf('%s_%s.%s', $uniqueId, $nameSlug, $format);
    }

    /**
     * @param string $uniqueId
     * @param string $nameSlug
     * @param string $width
     * @param string $height
     * @param string $format
     * @return string
     */
    private function getThumbUri($uniqueId, $nameSlug, $width, $height, $format)
    {
        return sprintf('%s_%s_%sx%s.%s', $uniqueId, $nameSlug, $width, $height, $format);
    }
}
