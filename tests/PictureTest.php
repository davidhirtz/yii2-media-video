<?php

declare(strict_types=1);

namespace Hirtz\Media\Video\Tests;

use Hirtz\Media\Test\Models\TestAsset;
use Hirtz\Media\Test\TestCase;
use Hirtz\Media\Test\Traits\MediaFixtureTrait;
use Hirtz\Media\Video\Widgets\Picture;
use Hirtz\Skeleton\Html\Img;
use Hirtz\Skeleton\Html\Video;

class PictureTest extends TestCase
{
    use MediaFixtureTrait;

    public function testImageTag(): void
    {
        $file = $this->getFileFromFixture('file-2');

        $asset = TestAsset::create();
        $asset->populateFileRelation($file);

        $expected = Img::make()
            ->src($file->getUrl())
            ->alt($file->alt_text)
            ->loading('lazy')
            ->render();

        self::assertEquals($expected, Picture::make()
            ->asset($asset)
            ->render());
    }

    public function testVideoTag(): void
    {
        $file = $this->getFileFromFixture('file-2');
        $file->extension = 'mp4';

        $asset = TestAsset::create();
        $asset->populateFileRelation($file);

        $expected = Video::make()
            ->attributes([
                'autoplay' => true,
                'data-src' => $file->getUrl(),
                'preload' => 'none',
                'playsinline' => true,
                'loop' => true,
                'muted' => "",
            ]);

        self::assertEquals($expected, Picture::make()
            ->asset($asset)
            ->videoAttributes(['autoplay' => true])
            ->render());
    }
}
