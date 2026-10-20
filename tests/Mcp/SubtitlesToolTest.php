<?php

declare(strict_types=1);

namespace Hirtz\Media\Video\Tests\Mcp;

use Hirtz\Media\Models\File;
use Hirtz\Media\Test\TestCase;
use Hirtz\Media\Test\Traits\MediaFileTrait;
use Hirtz\Media\Video\Mcp\GetSubtitlesTool;
use Hirtz\Media\Video\Mcp\SetSubtitlesTool;
use Hirtz\Skeleton\Helpers\FileHelper;
use Hirtz\Skeleton\Helpers\Url;
use Hirtz\Skeleton\Mcp\Mcp;
use Hirtz\Skeleton\Mcp\Tools\FindRecordsTool;
use Hirtz\Skeleton\Mcp\Tools\GetRecordTool;
use Hirtz\Skeleton\Mcp\Tools\ToolInterface;
use Hirtz\Skeleton\Test\Traits\UserFixtureTrait;
use Hirtz\Skeleton\Upload\Upload;
use Override;
use Yii;

class SubtitlesToolTest extends TestCase
{
    use MediaFileTrait;
    use UserFixtureTrait;

    private const string VTT = "WEBVTT\n\n00:00.000 --> 00:02.000\nÅsa, Łukasz, Привет, 字幕 🎬 Mu\u{308}ller\n";

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        Yii::$app->getI18n()->setLanguages(['en-US', 'de']);
        $this->folder = $this->createFolder('Uploads', 'uploads');
        $this->getWebUser()->setIdentity($this->getUserFromFixture('owner'));
    }

    #[Override]
    protected function tearDown(): void
    {
        FileHelper::removeDirectory((string)File::getModule()->uploadPath);
        FileHelper::removeDirectory(Upload::getComponent()->path);
        FileHelper::removeDirectory(Upload::getComponent()->tempPath);

        parent::tearDown();
    }

    public function testTheRecordAnswersEveryLanguage(): void
    {
        $file = $this->createFile('clip', 'mp4');
        $record = $this->call(new GetRecordTool(Mcp::getComponent()), ['resource' => 'file', 'id' => $file->id]);

        self::assertArrayHasKey('subtitles', $record['attributes'] ?? []);
        self::assertArrayHasKey('subtitles_de', $record['attributes'] ?? []);
        self::assertNull($record['attributes']['subtitles']);
    }

    public function testTheSubtitlesAreStoredReadReplacedAndRemoved(): void
    {
        $file = $this->createFile('clip', 'mp4');

        $result = $this->set($file, 'de', "\u{FEFF}" . self::VTT);
        self::assertFalse($result['isError'], json_encode($result['content'], JSON_UNESCAPED_UNICODE) ?: '');

        $file = File::findOne($file->id) ?? self::fail('No file');
        $url = Url::to(Upload::getComponent()->getUrl($file, 'subtitles_de', 'subtitles.vtt'), true);

        self::assertSame($url, $result['content']['url'] ?? null);
        self::assertSame(self::VTT, $this->get($file, 'de')['vtt'] ?? null);
        self::assertNull($this->get($file, 'en-US')['vtt'] ?? null);

        $record = $this->call(new GetRecordTool(Mcp::getComponent()), ['resource' => 'file', 'id' => $file->id]);
        self::assertSame($url, $record['attributes']['subtitles_de'] ?? null);

        $this->set($file, 'de', "WEBVTT\n\n00:00.000 --> 00:01.000\nNeu\n");
        self::assertStringContainsString('Neu', (string)($this->get($file, 'de')['vtt'] ?? ''));

        $path = Upload::getComponent()->getFilePath($file, 'subtitles_de', 'subtitles.vtt');
        self::assertFileExists($path);

        self::assertFalse($this->set($file, 'de', null)['isError']);
        self::assertNull($this->get($file, 'de')['vtt'] ?? null);
        self::assertFileDoesNotExist($path);
    }

    public function testMissingFindsTheVideosWithoutALanguage(): void
    {
        $complete = $this->createFile('complete', 'mp4');
        $this->set($complete, 'de', self::VTT);

        $sparse = $this->createFile('sparse', 'mp4');

        $ids = array_column($this->call(new FindRecordsTool(Mcp::getComponent()), [
            'resource' => 'file',
            'filter' => ['extension' => 'mp4'],
            'missing' => ['subtitles_de'],
        ])['records'] ?? [], 'id');

        self::assertContains($sparse->id, $ids);
        self::assertNotContains($complete->id, $ids);
    }

    public function testATextThatIsNotWebVttIsRefused(): void
    {
        $file = $this->createFile('clip', 'mp4');
        $result = $this->set($file, 'de', "1\n00:00:00,000 --> 00:00:02,000\nSRT\n");

        self::assertTrue($result['isError']);
        self::assertSame(
            ['The file is not a WebVTT file: it must begin with “WEBVTT”.'],
            $result['content']['errors']['subtitles_de'] ?? null,
        );
        self::assertNull(File::findOne($file->id)?->getAttribute('subtitles_de'));
    }

    public function testAFileThatIsNotAVideoIsRefused(): void
    {
        $file = $this->createFile('pic');
        $result = $this->set($file, 'de', self::VTT);

        self::assertTrue($result['isError']);
        self::assertSame("File $file->id is not a video.", $result['content']['error'] ?? null);
    }

    public function testAnUnknownLanguageIsRefused(): void
    {
        $result = $this->set($this->createFile('clip', 'mp4'), 'fr', self::VTT);

        self::assertTrue($result['isError']);
        self::assertSame('Unknown language, one of: en-US, de.', $result['content']['error'] ?? null);
    }

    public function testTheToolsNeedTheFilePermission(): void
    {
        $file = $this->createFile('clip', 'mp4');
        $this->getWebUser()->setIdentity($this->getUserFromFixture('admin'));

        $tool = new SetSubtitlesTool(Mcp::getComponent());

        self::assertFalse($tool->isAvailable());
        self::assertFalse((new GetSubtitlesTool(Mcp::getComponent()))->isAvailable());

        $result = $tool->call(['file' => $file->id, 'language' => 'de', 'vtt' => self::VTT]);

        self::assertTrue($result->isError);
        self::assertSame('Resource not found: file', $result->structuredContent['error'] ?? null);
    }

    /**
     * @return array{isError: bool, content: array<string, mixed>}
     */
    private function set(File $file, string $language, ?string $vtt): array
    {
        $result = (new SetSubtitlesTool(Mcp::getComponent()))->call(['file' => $file->id, 'language' => $language, 'vtt' => $vtt]);
        return ['isError' => $result->isError, 'content' => $result->structuredContent];
    }

    /**
     * @return array<string, mixed>
     */
    private function get(File $file, string $language): array
    {
        return $this->call(new GetSubtitlesTool(Mcp::getComponent()), ['file' => $file->id, 'language' => $language]);
    }

    /**
     * @param array<string, mixed> $arguments
     * @return array<string, mixed>
     */
    private function call(ToolInterface $tool, array $arguments): array
    {
        $result = $tool->call($arguments);
        self::assertFalse($result->isError, json_encode($result->structuredContent, JSON_UNESCAPED_UNICODE) ?: '');

        return $result->structuredContent;
    }
}
