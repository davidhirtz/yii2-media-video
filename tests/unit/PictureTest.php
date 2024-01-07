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

        $this->assertEquals($expected, Picture::tag($asset, [
            'transformations' => [],
        ]));
    }

    public function testVideoTag(): void
    {
        $file = $this->tester->grabFixture('file', 'video');

        $asset = TestAsset::create();
        $asset->populateFileRelation($file);

        $expected = Html::tag('video', '', [
            'src' => $file->getUrl(),
            'preload' => 'auto',
            'controls' => true,
            'playsinline' => true,
        ]);

        $this->assertEquals($expected, Picture::tag($asset, [
            'lazyVideoLoading' => false,
        ]));
    }
}
