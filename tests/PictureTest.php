<?php

declare(strict_types=1);

namespace Hirtz\Media\Video\Tests;

use Hirtz\Media\widgets\Picture;

/**
 * @property UnitTester $tester
 */
class PictureTest extends Unit
{
    public function _fixtures(): array
    {
        return [
            'file' => [
                'class' => FileFixture::class,
                'dataFile' => codecept_data_dir() . 'file.php',
            ],
        ];
    }

    public function testImageTag(): void
    {
        $file = $this->tester->grabFixture('file', 'image');

        $asset = TestAsset::create();
        $asset->populateFileRelation($file);

        $expected = Html::img($file->getUrl(), [
            'alt' => $file->alt_text,
            'loading' => 'lazy',
        ]);

        $this->assertEquals($expected, Picture::widget([
            'asset' => $asset,
            'transformations' => [],
        ]));
    }

    public function testVideoTag(): void
    {
        $file = $this->tester->grabFixture('file', 'video');

        $asset = TestAsset::create();
        $asset->populateFileRelation($file);

        $expected = Html::tag('video', '', [
            'autoplay' => true,
            'data-src' => $file->getUrl(),
            'preload' => 'none',
            'playsinline' => true,
            'loop' => true,
            'muted' => "",
        ]);

        $this->assertEquals($expected, Picture::widget([
            'asset' => $asset,
            'videoOptions' => [
                'autoplay' => true,
            ],
        ]));
    }
}
