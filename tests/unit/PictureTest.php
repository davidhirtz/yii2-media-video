<?php

namespace davidhirtz\yii2\media\video\tests\unit;

use Codeception\Test\Unit;
use davidhirtz\yii2\media\helpers\Html;
use davidhirtz\yii2\media\tests\support\UnitTester;
use davidhirtz\yii2\media\video\tests\fixtures\FileFixture;
use davidhirtz\yii2\media\video\tests\unit\models\TestAsset;
use davidhirtz\yii2\media\widgets\Picture;

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
