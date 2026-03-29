<?php

declare(strict_types=1);

namespace Hirtz\Media\Video\Tests;

use Hirtz\Media\Test\Models\TestAsset;
use Hirtz\Media\Test\TestCase;
use Hirtz\Media\Test\Traits\MediaFixtureTrait;
use Hirtz\Media\Video\Widgets\Media;
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

        self::assertEquals($expected, Media::make()
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
                'data-src' => $file->getUrl(),
                'autoplay' => true,
                'playsinline' => true,
                'loop' => true,
                'muted' => true,
            ])
            ->class('lazyload')
            ->render();

        self::assertEquals($expected, Media::make()
            ->asset($asset)
            ->autoplay()
            ->video(fn (Video $video) => $video->addClass('lazyload'))
            ->render());
    }
}
