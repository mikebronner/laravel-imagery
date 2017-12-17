<?php namespace GeneaLabs\LaravelImagery\Tests\Unit;

use GeneaLabs\LaravelImagery\Imagery;
use GeneaLabs\LaravelImagery\Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Intervention\Image\ImageManager;

class ImageryTest extends TestCase
{
    public function testUrlParameterReturnsString()
    {
        $image = (new Imagery)->conjure(public_path('test.jpg'));

        $result = $image->url;

        $this->assertInternalType('string', $result);
        $this->assertEquals('http://localhost/imagery-cache/test_500x375.jpg', $result);
    }

    public function testOriginalParameterReturnsFilePath()
    {
        $image = (new Imagery)->conjure(public_path('test.jpg'));

        $result = $image->source;

        $this->assertContains('/public/test.jpg', $result);
    }

    public function testOriginalParameterFromExternalUrlReturnsLocalUrl()
    {
        $image = (new Imagery)->conjure('http://cdn.skim.gs/images/fajkx3pdvvt9ax6btssg/20-of-the-cutest-small-dog-breeds-on-the-planet');
        $height = $image->height;
        $width = $image->width;

        $result = $image->originalUrl;

        $this->assertEquals("http://localhost/imagery-cache/20-of-the-cutest-small-dog-breeds-on-the-planet_{$width}x{$height}", $result);
    }

    public function testOriginalParameterFromExternalUrlSavesFileLocally()
    {
        $image = (new Imagery)->conjure('http://cdn.skim.gs/images/fajkx3pdvvt9ax6btssg/20-of-the-cutest-small-dog-breeds-on-the-planet');

        $content = file_get_contents($image->originalPath);

        $this->assertGreaterThan(0, strlen($content));
    }

    public function testExternalImageIsResizedByHeightWithRatioAndStoredLocally()
    {
        $width = 200;
        $height = 133;

        $conjuredImage = (new Imagery)->conjure('http://cdn.skim.gs/images/fajkx3pdvvt9ax6btssg/20-of-the-cutest-small-dog-breeds-on-the-planet', $width);
        $directImage = (new ImageManager)->make($conjuredImage->path);

        $this->assertEquals('http://localhost/imagery-cache/20-of-the-cutest-small-dog-breeds-on-the-planet_200x133', $conjuredImage->url);
        $this->assertEquals($width, $directImage->width());
        $this->assertEquals($height, $directImage->height());
    }
}
