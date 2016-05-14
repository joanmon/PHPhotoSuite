<?php

namespace PHPhotoSuit\Tests\PhotoSuite\Domain;

use PHPhotoSuit\PhotoSuite\Domain\Photo;
use PHPhotoSuit\PhotoSuite\Domain\PhotoCollection;

class PhotoCollectionTest extends \PHPUnit_Framework_TestCase
{
    /** @var PhotoCollection */
    private $photoCollection;

    public function setUp()
    {
        $this->photoCollection = new PhotoCollection();
        $this->photoCollection[] = $this->getPhoto();
    }

    /**
     * @test
     */
    public function collectionWorks()
    {
        $this->assertTrue(isset($this->photoCollection[0]));
        $this->assertEquals($this->photoCollection[0], $this->getPhoto());
    }

    /**
     * @test
     */
    public function exceptionWhenSettingBadObject()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->photoCollection[] = '';
    }

    /**
     * @test
     */
    public function exceptionWhenSettingBadObjectConstructor()
    {
        $this->expectException(\InvalidArgumentException::class);
        new PhotoCollection(['']);
    }

    /**
     * @test
     */
    public function foreEachCollection()
    {
        foreach ($this->photoCollection as $photo) {
            $this->assertEquals($photo, $this->getPhoto());
        }
    }

    private function getPhoto()
    {
        return $this->getMockBuilder(Photo::class)->disableOriginalConstructor()->getMock();
    }
}