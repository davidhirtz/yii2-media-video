<?php

declare(strict_types=1);

namespace Hirtz\Media\Video\Tests\Models\CustomAttributes;

use Hirtz\Media\Models\File;
use Hirtz\Media\Test\TestCase;
use Hirtz\Media\Test\Traits\MediaFileTrait;
use Hirtz\Media\Video\Models\CustomAttributes\SubtitlesCustomAttribute;
use Hirtz\Skeleton\Helpers\FileHelper;
use Hirtz\Skeleton\Upload\Upload;
use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use Yii;
use yii\web\UploadedFile;

class SubtitlesCustomAttributeTest extends TestCase
{
    use MediaFileTrait;

    private const string CUES = "WEBVTT\n\n00:00.000 --> 00:02.000\nÅsa och Łukasz: Привет, 字幕 🎬 Mu\u{308}ller\n";

    private Upload $upload;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        Yii::$app->getI18n()->setLanguages(['en-US', 'de']);
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

    #[DataProvider('provideAcceptedFiles')]
    public function testAWebVttFileIsAccepted(string $content): void
    {
        self::assertNull($this->validate('subtitles.vtt', $content));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function provideAcceptedFiles(): array
    {
        return [
            'plain' => [self::CUES],
            'bom' => ["\u{FEFF}" . self::CUES],
            'header text' => ["WEBVTT - Untertitel\n"],
            'header only' => ['WEBVTT'],
        ];
    }

    #[DataProvider('provideRefusedFiles')]
    public function testAnythingElseIsRefused(string $filename, string $content, string $error): void
    {
        self::assertSame($error, $this->validate($filename, $content));
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function provideRefusedFiles(): array
    {
        $webvtt = 'The file is not a WebVTT file: it must begin with “WEBVTT”.';
        $extension = 'Only files with these extensions are allowed: vtt.';

        return [
            'renamed text' => ['subtitles.vtt', "Nur Text, Åsa\n", $webvtt],
            'header without separator' => ['subtitles.vtt', "WEBVTTX\n", $webvtt],
            'empty' => ['subtitles.vtt', '', $webvtt],
            'srt' => ['subtitles.srt', "1\n00:00:00,000 --> 00:00:02,000\nText\n", $extension],
            'txt' => ['subtitles.txt', self::CUES, $extension],
        ];
    }

    public function testTheAttributeIsTranslatedAndVisibleOnAVideoOnly(): void
    {
        $safe = $this->createFile('clip', 'mp4')->safeAttributes();

        self::assertContains('subtitles', $safe);
        self::assertContains('subtitles_de', $safe);
        self::assertNotContains('subtitles', $this->createFile('pic')->safeAttributes());
    }

    public function testTheUploadIsGuardedByTheFilePermission(): void
    {
        $definition = $this->createFile('clip', 'mp4')->getCustomAttributeDefinitions()['subtitles'] ?? null;

        self::assertInstanceOf(SubtitlesCustomAttribute::class, $definition);
        self::assertSame(File::AUTH_FILE, $definition->getPermission());
    }

    public function testTheFilesAreStoredAndRemovedWithTheFile(): void
    {
        $file = $this->createFile('clip', 'mp4');
        $token = $this->createToken('subtitles.vtt', self::CUES);
        $file->setAttribute('subtitles', $token);
        $file->setAttribute('subtitles_de', $this->createToken('untertitel.vtt', "\u{FEFF}" . self::CUES));

        self::assertSame(1, $file->update(), print_r($file->getErrors(), true));
        self::assertNull($this->upload->getTempFile($token));

        $file = File::findOne($file->id);
        self::assertNotNull($file);
        self::assertSame('subtitles.vtt', $file->getAttribute('subtitles'));
        self::assertSame('untertitel.vtt', $file->getAttribute('subtitles_de'));

        $path = $this->upload->getFilePath($file, 'subtitles', 'subtitles.vtt');
        self::assertSame(self::CUES, file_get_contents($path));
        self::assertFileExists($this->upload->getFilePath($file, 'subtitles_de', 'untertitel.vtt'));

        self::assertNotFalse($file->delete());
        self::assertFileDoesNotExist($path);
    }

    private function validate(string $filename, string $content): ?string
    {
        $path = $this->upload->tempPath . $this->createToken($filename, $content);

        $definition = $this->createFile('clip', 'mp4')->getCustomAttributeDefinitions()['subtitles'] ?? null;
        self::assertInstanceOf(SubtitlesCustomAttribute::class, $definition);

        return $definition->validateUploadedFile(new UploadedFile([
            'name' => $filename,
            'tempName' => $path,
            'type' => 'text/plain',
            'size' => strlen($content),
            'error' => UPLOAD_ERR_OK,
        ]));
    }

    private function createToken(string $filename, string $content): string
    {
        $token = 'abcdefghijklmnop_' . $filename;

        FileHelper::createDirectory($this->upload->tempPath);
        file_put_contents($this->upload->tempPath . $token, $content);

        return $token;
    }
}
