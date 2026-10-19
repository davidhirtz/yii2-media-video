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
use Hirtz\Media\Video\Assets\VideoPreviewAssetBundle;
use Hirtz\Skeleton\Web\Application;
use Hirtz\Skeleton\Widgets\Buttons\FileUploadButton;

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

    public function testTheThumbnailOfAVideoWithoutPreviewIsAFrameTheBrowserDraws(): void
    {
        $file = $this->getVideoFile();
        $file->transformation_count = 0;

        $html = Thumbnail::make()
            ->file($file)
            ->render();

        self::assertStringContainsString('<video class="img-thumbnail" src="' . $file->getUrl() . '#t=1"', $html);
        self::assertStringContainsString('preload="metadata"', $html);
        self::assertStringContainsString('class="thumbnail-play"', $html);
    }

    public function testTheThumbnailOfAVideoShowsItsPreview(): void
    {
        $file = $this->getVideoFile();
        $file->transformation_count = 1;

        $html = Thumbnail::make()
            ->file($file)
            ->render();

        self::assertStringContainsString('src="' . $file->getPreviewUrl() . '"', $html);
        self::assertStringNotContainsString('<video', $html);
        self::assertStringContainsString('class="thumbnail-play"', $html);
    }

    public function testTheUploadButtonLoadsTheScriptDrawingAPreview(): void
    {
        FileUploadButton::make()
            ->url(['/admin/media/file/create'])
            ->render();

        self::assertArrayHasKey(VideoPreviewAssetBundle::class, Application::current()->getView()->assetBundles);
    }

    private function getVideoFile(): File
    {
        $file = $this->getFileFromFixture('file-2');
        $file->extension = 'mp4';
        $file->setOldAttribute('extension', 'mp4');

        return $file;
    }
}
