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

    /**
     * Without autoplay the browser is told to fetch the video, and an eagerly loaded one carries a real `src`.
     */
    public function testAVideoThatIsNotAutoplayed(): void
    {
        $file = $this->getFileFromFixture('file-2');
        $file->extension = 'mp4';

        $asset = TestAsset::create();
        $asset->populateFileRelation($file);

        $html = (string)Media::make()
            ->asset($asset)
            ->lazyLoading(false);

        self::assertStringContainsString('preload="auto"', $html);
        self::assertStringContainsString('src="' . $file->getUrl() . '"', $html);
        self::assertStringNotContainsString('autoplay', $html);

        $html = (string)Media::make()->asset($asset);

        self::assertStringContainsString('preload="none"', $html);
        self::assertStringContainsString('data-src=', $html);
    }

    public function testTheAspectRatioIsWrittenOnTheVideo(): void
    {
        $file = $this->getFileFromFixture('file-2');
        $file->extension = 'mp4';
        $file->width = 1920;
        $file->height = 1080;

        $asset = TestAsset::create();
        $asset->populateFileRelation($file);

        $html = (string)Media::make()
            ->asset($asset)
            ->aspectRatio(true);

        self::assertStringContainsString('aspect-ratio', $html);
    }
}
