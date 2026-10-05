<?php

declare(strict_types=1);

namespace Hirtz\Media\Video\Tests\Modules\Admin\Widgets;

use Hirtz\Media\Models\File;
use Hirtz\Media\Modules\Admin\Widgets\Forms\Fields\AssetPreviewField;
use Hirtz\Media\Modules\Admin\Widgets\Forms\Fields\FilePreviewField;
use Hirtz\Media\Modules\Admin\Widgets\Grids\Columns\Thumbnail;
use Hirtz\Media\Test\Models\TestAsset;
use Hirtz\Media\Test\TestCase;
use Hirtz\Media\Test\Traits\MediaFixtureTrait;

/**
 * Every admin preview of a file goes through a class the bundle swaps in through the container.
 */
class PreviewTest extends TestCase
{
    use MediaFixtureTrait;

    public function testTheFilePreviewFieldPlaysAVideo(): void
    {
        $html = FilePreviewField::make()
            ->file($this->getVideoFile())
            ->render();

        self::assertStringContainsString('<video', $html);
    }

    public function testTheAssetPreviewFieldPlaysAVideo(): void
    {
        $asset = TestAsset::create();
        $asset->populateFileRelation($this->getVideoFile());

        $html = AssetPreviewField::make()
            ->asset($asset)
            ->render();

        self::assertStringContainsString('<video', $html);
    }

    public function testTheAssetPreviewFieldShowsAnImage(): void
    {
        $file = $this->getFileFromFixture('file-2');

        $asset = TestAsset::create();
        $asset->populateFileRelation($file);

        $html = AssetPreviewField::make()
            ->asset($asset)
            ->render();

        self::assertStringContainsString('data-id="image"', $html);
        self::assertStringNotContainsString('<video', $html);
    }

    public function testTheThumbnailShowsAVideo(): void
    {
        $html = Thumbnail::make()
            ->file($this->getVideoFile())
            ->render();

        self::assertStringContainsString('video.svg', $html);
    }

    private function getVideoFile(): File
    {
        $file = $this->getFileFromFixture('file-2');
        $file->extension = 'mp4';
        $file->setOldAttribute('extension', 'mp4');

        return $file;
    }
}
