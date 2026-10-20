<?php

declare(strict_types=1);

namespace Hirtz\Media\Video\Tests\Widgets;

use Hirtz\Media\Models\File;
use Hirtz\Media\Modules\Admin\Widgets\Forms\Fields\FilePreviewField;
use Hirtz\Media\Test\Models\TestAsset;
use Hirtz\Media\Test\TestCase;
use Hirtz\Media\Test\Traits\MediaFileTrait;
use Hirtz\Media\Video\Widgets\Media;
use Hirtz\Skeleton\Helpers\FileHelper;
use Hirtz\Skeleton\Upload\Upload;
use Override;
use Yii;

class SubtitleTracksTest extends TestCase
{
    use MediaFileTrait;

    private Upload $upload;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        Yii::$app->getI18n()->setLanguages(['en-US', 'de', 'fr']);
        $this->upload = Upload::getComponent();
        $this->folder = $this->createFolder('Uploads', 'uploads');
    }

    #[Override]
    protected function tearDown(): void
    {
        FileHelper::removeDirectory((string)File::getModule()->uploadPath);
        FileHelper::removeDirectory($this->upload->path);
        FileHelper::removeDirectory($this->upload->tempPath);

        parent::tearDown();
    }

    public function testEachStoredLanguageIsATrack(): void
    {
        $file = $this->createVideoWithSubtitles();
        $html = $this->renderMedia($file);

        $en = $this->upload->getUrl($file, 'subtitles', 'subtitles.vtt');
        $de = $this->upload->getUrl($file, 'subtitles_de', 'untertitel.vtt');

        self::assertStringContainsString(
            "<track src=\"$en\" kind=\"subtitles\" srclang=\"en-US\" label=\"English\" default>"
            . "<track src=\"$de\" kind=\"subtitles\" srclang=\"de\" label=\"Deutsch\"></video>",
            $html,
        );

        self::assertSame(2, substr_count($html, '<track'));
    }

    public function testTheTrackOfTheApplicationLanguageIsTheDefault(): void
    {
        $file = $this->createVideoWithSubtitles();

        Yii::$app->language = 'de';
        $html = $this->renderMedia($file);

        self::assertSame(1, substr_count($html, ' default>'));
        self::assertStringContainsString('srclang="de" label="Deutsch" default>', $html);
    }

    public function testNoTrackWithoutSubtitles(): void
    {
        $file = $this->createVideoWithSubtitles();

        self::assertStringNotContainsString('<track', (string)Media::make()
            ->asset($this->createAsset($file))
            ->subtitles(false));
    }

    public function testNoTrackOnAnImage(): void
    {
        $file = $this->createVideoWithSubtitles();
        $file->extension = 'jpg';

        self::assertStringNotContainsString('<track', $this->renderMedia($file));
    }

    public function testTheAdminPreviewPlaysTheTracks(): void
    {
        $file = $this->createVideoWithSubtitles();

        $html = FilePreviewField::make()
            ->file($file)
            ->render();

        self::assertSame(2, substr_count($html, '<track'));
    }

    private function createVideoWithSubtitles(): File
    {
        $file = $this->createFile('clip', 'mp4');
        $file->setAttribute('subtitles', $this->createToken('subtitles.vtt'));
        $file->setAttribute('subtitles_de', $this->createToken('untertitel.vtt'));

        self::assertSame(1, $file->update(), print_r($file->getErrors(), true));

        return $file;
    }

    private function renderMedia(File $file): string
    {
        return (string)Media::make()
            ->asset($this->createAsset($file))
            ->lazyLoading(false);
    }

    private function createAsset(File $file): TestAsset
    {
        $asset = TestAsset::create();
        $asset->populateFileRelation($file);

        return $asset;
    }

    private function createToken(string $filename): string
    {
        $token = 'abcdefghijklmnop_' . $filename;

        FileHelper::createDirectory($this->upload->tempPath);
        file_put_contents($this->upload->tempPath . $token, "WEBVTT\n");

        return $token;
    }
}
