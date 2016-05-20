<?php

namespace PHPhotoSuit\PhotoSuite\Infrastructure\Persistence;

use PHPhotoSuit\PhotoSuite\Domain\Exception\CollectionNotFoundException;
use PHPhotoSuit\PhotoSuite\Domain\Exception\PhotoNotFoundException;
use PHPhotoSuit\PhotoSuite\Domain\HttpUrl;
use PHPhotoSuit\PhotoSuite\Domain\Lang;
use PHPhotoSuit\PhotoSuite\Domain\Model\Photo;
use PHPhotoSuit\PhotoSuite\Domain\Model\PhotoAlt;
use PHPhotoSuit\PhotoSuite\Domain\Model\PhotoAltCollection;
use PHPhotoSuit\PhotoSuite\Domain\Model\PhotoCollection;
use PHPhotoSuit\PhotoSuite\Domain\Model\PhotoFile;
use PHPhotoSuit\PhotoSuite\Domain\Model\PhotoId;
use PHPhotoSuit\PhotoSuite\Domain\Model\PhotoName;
use PHPhotoSuit\PhotoSuite\Domain\Model\PhotoRepository;
use PHPhotoSuit\PhotoSuite\Domain\ResourceId;

class SqlitePhotoRepository implements PhotoRepository
{
    /** @var SqliteConfig */
    private $sqliteConfig;
    /** @var \PDO */
    private $pdo;

    /**
     * @param SqliteConfig $sqliteConfig
     */
    public function __construct(SqliteConfig $sqliteConfig)
    {
        $this->sqliteConfig = $sqliteConfig;
        $this->pdo = new \PDO('sqlite:' . $this->sqliteConfig->dbPath());
    }

    /**
     * @inheritdoc
     */
    public function initialize()
    {
        $createPhotoTable =<<<SQL
CREATE TABLE IF NOT EXISTS "Photo" (
    "uuid" TEXT NOT NULL PRIMARY KEY,
    "resourceId" TEXT NOT NULL,
    "name" TEXT NOT NULL,
    "httpUrl" TEXT NOT NULL,
    "filePath" TEXT
)
SQL;
        $this->pdo->query($createPhotoTable);
        $createAlternativeTextTable =<<<SQL
CREATE TABLE IF NOT EXISTS "AlternativeText" (
    "photo_uuid" TEXT NOT NULL,
    "alt" TEXT NOT NULL,
    "lang" TEXT NOT NULL,
    FOREIGN KEY(photo_uuid) REFERENCES Photo(uuid)
)
SQL;
        $this->pdo->query($createAlternativeTextTable);
        $this->pdo->query("CREATE INDEX resource ON \"Photo\" (resourceId);");
    }

    /**
     * @param ResourceId $resourceId
     * @return Photo
     * @throws PhotoNotFoundException
     */
    public function findOneBy(ResourceId $resourceId)
    {
        $sentence  = $this->pdo->prepare("SELECT * FROM \"Photo\" WHERE resourceId=:resourceId LIMIT 1");
        $sentence->bindParam(':resourceId', $resourceId->id(), \PDO::PARAM_STR);
        $sentence->execute();
        $row = $sentence->fetch(\PDO::FETCH_ASSOC);
        if ($row) {
            return $this->createPhotoByRow($row);
        }
        throw new PhotoNotFoundException(sprintf('Photo with resource id %s not found', $resourceId->id()));
    }

    /**
     * @param PhotoId $photoId
     * @return mixed
     * @throws PhotoNotFoundException
     */
    public function findById(PhotoId $photoId)
    {
        $sentence  = $this->pdo->prepare("SELECT * FROM \"Photo\" WHERE uuid=:uuid LIMIT 1");
        $sentence->bindParam(':uuid', $photoId->id());
        $sentence->execute();
        $row = $sentence->fetch(\PDO::FETCH_ASSOC);
        if ($row) {
            return $this->createPhotoByRow($row);
        }
        throw new PhotoNotFoundException(sprintf('Photo with uuid %s not found', $photoId->id()));
    }

    /**
     * @param ResourceId $resourceId
     * @return PhotoCollection;
     * @throws CollectionNotFoundException
     */
    public function findCollectionBy(ResourceId $resourceId)
    {
        $sentence  = $this->pdo->prepare("SELECT * FROM \"Photo\" WHERE resourceId=:resourceId");
        $sentence->bindParam(':resourceId', $resourceId->id(), \PDO::PARAM_STR);
        $sentence->execute();
        $rows = $sentence->fetchAll(\PDO::FETCH_ASSOC);
        if ($rows) {
            $photos = new PhotoCollection();
            foreach ($rows as $row) {
                $photos[] = $this->createPhotoByRow($row);
            }
            return $photos;
        }
        throw new CollectionNotFoundException(sprintf('Photos with resource id %s not found', $resourceId->id()));
    }

    /**
     * @param Photo $photo
     * @return void
     */
    public function save(Photo $photo)
    {
        $sentence = $this->pdo->prepare(
            "INSERT INTO Photo(\"uuid\", \"resourceId\", \"name\", \"httpUrl\", \"filePath\") " .
            "VALUES(:uuid, :resourceId, :name, :httpUrl, :filePath)"
        );
        $sentence->bindParam(':uuid', $photo->id());
        $sentence->bindParam(':resourceId', $photo->resourceId());
        $sentence->bindParam(':name', $photo->name());
        $sentence->bindParam(':httpUrl', $photo->getPhotoHttpUrl());
        $filePath = is_null($photo->photoFile()) ? null : $photo->photoFile()->filePath();
        $filePathType = is_null($photo->photoFile()) ? \PDO::PARAM_NULL : \PDO::PARAM_STR;
        $sentence->bindParam(':filePath', $filePath, $filePathType);
        $sentence->execute();

        foreach ($photo->altCollection() as $photoAlt) {
            $this->saveAlternativeText(new PhotoId($photo->id()), $photoAlt);
        }
    }

    /**
     * @param PhotoId $photoId
     * @param PhotoAlt $alt
     */
    private function saveAlternativeText(PhotoId $photoId, PhotoAlt $alt)
    {
        $sentence = $this->pdo->prepare(
            "INSERT INTO AlternativeText(\"photo_uuid\", \"alt\", \"lang\") " .
            "VALUES(:photo_uuid, :alt, :lang)"
        );
        $sentence->bindParam(':photo_uuid', $photoId->id());
        $sentence->bindParam(':alt', $alt->name());
        $sentence->bindParam(':lang', $alt->lang());
        $sentence->execute();
    }

    /**
     * @param Photo $photo
     * @return void
     */
    public function delete(Photo $photo)
    {
        $sentence = $this->pdo->prepare("DELETE FROM Photo WHERE uuid=:uuid LIMIT 1");
        $sentence->bindParam(':uuid', $photo->id());
        $sentence->execute();
    }

    /**
     * @param $row
     * @return Photo
     */
    public function createPhotoByRow($row)
    {
        $photoFile = !empty($row['filePath']) ? new PhotoFile($row['filePath']) : null;
        $photoId = new PhotoId($row['uuid']);
        return new Photo(
            new PhotoId($row['uuid']),
            new ResourceId($row['resourceId']),
            new PhotoName($row['name']),
            new HttpUrl($row['httpUrl']),
            $this->getAltCollectionBy($photoId),
            $photoFile
        );
    }

    /**
     * @param PhotoId|null $photoId
     * @return PhotoId
     */
    public function ensureUniquePhotoId(PhotoId $photoId = null)
    {
        $photoId = is_null($photoId) ? new PhotoId() : $photoId;
        $sentence  = $this->pdo->prepare("SELECT uuid FROM \"Photo\" WHERE uuid=:uuid LIMIT 1");
        $sentence->bindParam(':uuid', $photoId->id());
        $sentence->execute();
        $row = $sentence->fetch(\PDO::FETCH_ASSOC);
        if ($row) {
            return $this->ensureUniquePhotoId();
        }
        return $photoId;
    }

    /**
     * @param PhotoId $photoId
     * @return PhotoAltCollection
     */
    private function getAltCollectionBy(PhotoId $photoId)
    {
        $sentence  = $this->pdo->prepare("SELECT * FROM \"AlternativeText\" WHERE photo_uuid=:uuid");
        $sentence->bindParam(':uuid', $photoId->id());
        $sentence->execute();
        $photoAltCollection = new PhotoAltCollection();
        $rows = $sentence->fetchAll(\PDO::FETCH_ASSOC);
        if ($rows) {
            foreach ($rows as $row) {
                $photoAltCollection[] = new PhotoAlt($row['alt'], new Lang($row['lang']));
            }
        }
        return $photoAltCollection;
    }
}
