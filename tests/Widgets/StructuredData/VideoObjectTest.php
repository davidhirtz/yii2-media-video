<?php

declare(strict_types=1);

namespace Hirtz\Media\Video\Tests\Widgets\StructuredData;

use Hirtz\Media\Models\File;
use Hirtz\Media\Test\TestCase;
use Hirtz\Media\Test\Traits\MediaFileTrait;
use Hirtz\Media\Video\Streaming\StreamManager;
use Hirtz\Media\Video\Streaming\StreamStatus;
use Hirtz\Media\Video\Test\FakeStreamProvider;
use Hirtz\Media\Video\Widgets\StructuredData\VideoObject;
use Hirtz\Skeleton\Helpers\FileHelper;
use Override;
use Yii;

class VideoObjectTest extends TestCase
{
    use MediaFileTrait;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->folder = $this->createFolder('Uploads', 'uploads');
    }

    #[Override]
    protected function tearDown(): void
    {
        FileHelper::removeDirectory((string)File::getModule()->uploadPath);
        parent::tearDown();
    }

    public function testAVideoFillsItsNodeFromTheFile(): void
    {
        $file = $this->insertVideo();
        $file->alt_text = 'Mu' . "\u{308}" . 'llers Å clip 🎬';

        $node = VideoObject::make()
            ->file($file)
            ->thumbnailUrl('/poster.jpg')
            ->build();

        self::assertSame([
            '@type' => 'VideoObject',
            'name' => 'Clip',
            'description' => 'Mu' . "\u{308}" . 'llers Å clip 🎬',
            'thumbnailUrl' => 'https://www.test.localhost/poster.jpg',
            'uploadDate' => $file->created_at->format(DATE_ATOM),
            'duration' => 'PT3S',
            'contentUrl' => 'https://www.test.localhost' . $file->getUrl(),
            'width' => $file->width,
            'height' => $file->height,
        ], $node);
    }

    public function testTheDurationIsIso8601(): void
    {
        $file = $this->insertVideo();

        $file->setAttribute('duration', 3665);
        self::assertSame('PT1H1M5S', VideoObject::make()->file($file)->thumbnailUrl('/p.jpg')->build()['duration'] ?? null);

        $file->setAttribute('duration', 3600);
        self::assertSame('PT1H', VideoObject::make()->file($file)->thumbnailUrl('/p.jpg')->build()['duration'] ?? null);

        $file->setAttribute('duration', 0);
        self::assertSame('PT0S', VideoObject::make()->file($file)->thumbnailUrl('/p.jpg')->build()['duration'] ?? null);
    }

    public function testAReadyStreamsPosterIsTheThumbnail(): void
    {
        $this->config['components']['videoStream'] = [
            'class' => StreamManager::class,
            'provider' => ['class' => FakeStreamProvider::class],
        ];

        $this->reloadApplication();
        $this->folder = $this->createFolder('Videos', 'videos');

        $file = $this->insertVideo();
        $manager = StreamManager::getComponent();
        $state = $manager->getState($file) ?? self::fail('No stream state');

        self::assertNull(VideoObject::make()->file($file)->build());

        $manager->saveState($file, $state->withStatus(StreamStatus::Ready));
        $node = VideoObject::make()->file($file)->build();

        self::assertSame("https://stream.example.com/$state->id/thumbnail.jpg", $node['thumbnailUrl'] ?? null);
    }

    public function testAVideoWithoutAThumbnailOrAnImageRendersNothing(): void
    {
        self::assertNull(VideoObject::make()->build());
        self::assertNull(VideoObject::make()->file($this->insertVideo())->build());
        self::assertNull(VideoObject::make()->file($this->createFile('pic'))->thumbnailUrl('/p.jpg')->build());
        self::assertNull(VideoObject::make()->file($this->insertVideo('other'))->name('')->thumbnailUrl('/p.jpg')->build());
    }

    private function insertVideo(string $name = 'clip'): File
    {
        $file = File::create();
        $file->loadDefaultValues();
        $file->name = ucfirst($name);
        $file->populateFolderRelation($this->folder);

        $path = Yii::getAlias('@runtime') . "/$name.mp4";
        copy(__DIR__ . '/../../Data/clip.mp4', $path);

        try {
            self::assertTrue($file->copy($path));
            self::assertTrue($file->insert(), print_r($file->getErrors(), true));
        } finally {
            FileHelper::unlink($path);
        }

        return $file;
    }
}
