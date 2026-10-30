<?php

declare(strict_types=1);

namespace Hirtz\Media\Video\Tests\Streaming;

use Hirtz\Media\Models\File;
use Hirtz\Media\Modules\Admin\Widgets\Forms\Fields\FilePreviewField;
use Hirtz\Media\Modules\Admin\Widgets\Grids\Columns\Thumbnail;
use Hirtz\Media\Test\Models\TestAsset;
use Hirtz\Media\Test\TestCase;
use Hirtz\Media\Test\Traits\MediaFileTrait;
use Hirtz\Media\Video\Assets\HlsAssetBundle;
use Hirtz\Media\Video\Streaming\StreamManager;
use Hirtz\Media\Video\Streaming\StreamStatus;
use Hirtz\Media\Video\Test\FakeStreamProvider;
use Hirtz\Media\Video\Widgets\Media;
use Hirtz\Skeleton\Helpers\FileHelper;
use Hirtz\Skeleton\Html\Span;
use Hirtz\Skeleton\Test\Browser;
use Hirtz\Skeleton\Test\Traits\FunctionalTestTrait;
use Hirtz\Skeleton\Test\Traits\UserFixtureTrait;
use Hirtz\Skeleton\Web\Application;
use Override;
use Yii;

class StreamRenderingTest extends TestCase
{
    use FunctionalTestTrait;
    use MediaFileTrait;
    use UserFixtureTrait;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->config['components']['videoStream'] = [
            'class' => StreamManager::class,
            'provider' => ['class' => FakeStreamProvider::class],
        ];

        $this->reloadApplication();
        $this->folder = $this->createFolder('Uploads', 'uploads');
    }

    #[Override]
    protected function tearDown(): void
    {
        FileHelper::removeDirectory((string)File::getModule()->uploadPath);
        parent::tearDown();
    }

    public function testAReadyStreamPlaysThePlaylist(): void
    {
        $file = $this->insertReadyVideo();
        $state = StreamManager::getComponent()->getState($file);
        $html = $this->renderMedia($file, false);

        self::assertStringContainsString("src=\"https://stream.example.com/$state?->id/playlist.m3u8\"", $html);
        self::assertStringContainsString("poster=\"https://stream.example.com/$state?->id/thumbnail.jpg\"", $html);
        self::assertStringContainsString(' data-hls', $html);
        self::assertStringContainsString('data-fallback="' . $file->getUrl() . '"', $html, 'The local file plays when the stream does not load.');
        self::assertArrayHasKey(HlsAssetBundle::class, Application::current()->getView()->assetBundles);
    }

    public function testALazyStreamLeavesThePlaylistToTheLoader(): void
    {
        $html = $this->renderMedia($this->insertReadyVideo(), true);

        self::assertMatchesRegularExpression('#data-src="https://stream\.example\.com/[^"]+/playlist\.m3u8"#', $html);
        self::assertStringNotContainsString(' src=', $html);
    }

    public function testAProjectPosterWins(): void
    {
        $html = (string)Media::make()
            ->asset($this->createAsset($this->insertReadyVideo()))
            ->lazyLoading(false)
            ->video(static fn ($video) => $video->attribute('poster', '/poster.jpg'));

        self::assertStringContainsString('poster="/poster.jpg"', $html);
    }

    public function testAStreamNotReadyPlaysTheLocalFile(): void
    {
        $file = $this->insertVideo();
        $html = $this->renderMedia($file, false);

        self::assertStringContainsString('src="' . $file->getUrl() . '"', $html);
        self::assertStringNotContainsString('data-hls', $html);
        self::assertArrayNotHasKey(HlsAssetBundle::class, Application::current()->getView()->assetBundles);
    }

    public function testTheAdminPreviewPlaysTheStream(): void
    {
        $html = FilePreviewField::make()
            ->file($this->insertReadyVideo())
            ->render();

        self::assertMatchesRegularExpression('#<video[^>]+src="https://stream\.example\.com/[^"]+/playlist\.m3u8"[^>]+data-hls#', $html);
    }

    public function testTheThumbnailFallsBackToThePoster(): void
    {
        $file = $this->insertReadyVideo();
        $file->transformation_count = 0;

        $html = Thumbnail::make()
            ->file($file)
            ->render();

        self::assertMatchesRegularExpression('#<img[^>]+src="https://stream\.example\.com/[^"]+/thumbnail\.jpg"#', $html);
    }

    public function testTheWebhookRefreshesAKnownVideo(): void
    {
        $file = $this->insertVideo();
        $provider = $this->getProvider();
        $provider->status = StreamStatus::Ready;

        $this->post('/video-stream/webhook', ['id' => StreamManager::getComponent()->getState($file)?->id]);

        self::assertResponseStatusCodeSame(204);
        self::assertResponseNotHasHeader('set-cookie');
        self::assertCount(1, $provider->getCalls('fetchState'));
        self::assertSame(StreamStatus::Ready, StreamManager::getComponent()->getState(File::findOne($file->id) ?? self::fail('No file'))?->status);
    }

    public function testTheWebhookAnswersAnUnknownOrMalformedIdTheSame(): void
    {
        $this->insertVideo();

        $this->post('/video-stream/webhook', ['id' => 'unknown']);
        self::assertResponseStatusCodeSame(204);

        $this->post('/video-stream/webhook', ['id' => ['x']]);
        self::assertResponseStatusCodeSame(204);

        $this->post('/video-stream/webhook');
        self::assertResponseStatusCodeSame(204);

        self::assertSame([], $this->getProvider()->getCalls('fetchState'));
    }

    public function testTheWebhookIsPostOnly(): void
    {
        $this->open('/video-stream/webhook');
        self::assertResponseStatusCodeSame(405);
    }

    public function testAnEditorResubmitsAVideo(): void
    {
        $file = $this->insertVideo();
        $old = StreamManager::getComponent()->getState($file)?->id;

        $user = $this->getUserFromFixture('admin');
        $this->assignAdminRole($user->id);
        $this->getWebUser()->login($user);

        $this->open("/admin/media/file/update?id=$file->id");
        self::assertAnySelectorTextContains('.dropdown-item .btn', 'Resubmit stream');

        $this->postResubmit($file);

        self::assertCurrentUrlEquals("https://www.test.localhost/admin/media/file/update?id=$file->id");
        self::assertAlertSame('The video was submitted to the streaming provider.', 'success');
        self::assertSame([['delete', $old]], $this->getProvider()->getCalls('delete'));
    }

    public function testTheFileGridShowsTheStreamStatus(): void
    {
        $ready = $this->insertReadyVideo();
        $this->getProvider()->submitStatus = StreamStatus::Failed;
        $this->getProvider()->error = '<b>Ошибка</b> 🎬';
        $failed = $this->insertVideo('failed');

        $this->loginAdmin();
        $this->open('/admin/media/file/index');

        self::assertSelectorExists("#file-{$ready->id} .thumbnail-video > .thumbnail-badges > .badge-success [title=\"Stream: Ready\"]");
        self::assertSelectorExists("#file-{$failed->id} .thumbnail-video .badge-error [title=\"Stream: Failed: <b>Ошибка</b> 🎬\"]");
    }

    public function testTheStreamBadgeJoinsTheThumbnailsBadges(): void
    {
        $file = $this->insertReadyVideo();

        $html = (string)Thumbnail::make()
            ->file($file)
            ->badges(Span::make()->class('badge badge-type'));

        self::assertSame(1, substr_count($html, 'thumbnail-badges'));
        self::assertMatchesRegularExpression('#<div class="thumbnail-badges"><span class="badge badge-type"></span><span class="badge badge-success">#', $html);
    }

    public function testTheFilePageDetailsTheStreamAndLinksTheProvider(): void
    {
        $file = $this->insertReadyVideo();
        $id = StreamManager::getComponent()->getState($file)->id ?? self::fail('No id.');

        $this->loginAdmin();
        $this->open("/admin/media/file/update?id=$file->id");

        self::assertSelectorTextContains('#file-stream-row .form-content > div > .badge-success', 'Ready');
        self::assertSelectorTextContains('#file-stream-row .form-content > .text-muted', "Fake Stream · $id");
        self::assertSame(2, self::$crawler->filter('#file-stream-row x-timeago[date]')->count());
        self::assertSelectorTextContains('#file-stream-row', 'Submitted ');
        self::assertSelectorExists("#file-stream-row a[href=\"https://stream.example.com/dashboard/$id\"][target=\"_blank\"][rel=\"noopener\"][title=\"Open at Fake Stream\"]");
    }

    public function testTheFilePageEncodesAProvidersErrorAndLinksNoForeignVideo(): void
    {
        $this->getProvider()->submitStatus = StreamStatus::Failed;
        $this->getProvider()->error = '<script>alert(1)</script> Mu' . "\u{308}" . 'ller';
        $file = $this->insertVideo();

        $this->getProvider()->scope = 'another-library';

        $this->loginAdmin();
        $this->open("/admin/media/file/update?id=$file->id");

        $html = (string)self::$client->getResponse()->getContent();

        self::assertStringNotContainsString('<script>alert(1)</script>', $html);
        self::assertSelectorTextContains('#file-stream-row .badge-warning', 'Streamed by another installation');
        self::assertSelectorNotExists('#file-stream-row a');
    }

    public function testAPlayableStreamOffersNoResubmit(): void
    {
        $file = $this->insertReadyVideo();

        $user = $this->getUserFromFixture('admin');
        $this->assignAdminRole($user->id);
        $this->getWebUser()->login($user);

        $this->open("/admin/media/file/update?id=$file->id");
        self::assertAnySelectorTextNotContains('.dropdown-item .btn', 'Resubmit stream');

        $this->postResubmit($file);

        self::assertResponseStatusCodeSame(404);
        self::assertSame([], $this->getProvider()->getCalls('delete'));
    }

    public function testTheResubmitNeedsThePermission(): void
    {
        $file = $this->insertVideo();

        $this->getWebUser()->login($this->getUserFromFixture('admin'));

        $this->open('/admin/account/update');
        $this->postResubmit($file);

        self::assertResponseStatusCodeSame(403);
        self::assertCount(1, $this->getProvider()->getCalls('submit'));
    }

    public function testTheAdminPolicyAdmitsTheProvider(): void
    {
        $this->config['components']['contentSecurityPolicy']['directives']['default-src'] = ["'self'"];
        $this->reloadApplication();

        $user = $this->getUserFromFixture('admin');
        $this->assignAdminRole($user->id);
        $this->getWebUser()->login($user);

        $this->open('/admin/media/file/index');

        $policy = self::$client->getResponse()->getHeader('content-security-policy');

        self::assertIsString($policy);
        self::assertStringContainsString("media-src 'self' https://stream.example.com blob:", $policy);
    }

    public function testAnImageOffersNoResubmit(): void
    {
        $file = $this->createFile('pic');

        $user = $this->getUserFromFixture('admin');
        $this->assignAdminRole($user->id);
        $this->getWebUser()->login($user);

        $this->open("/admin/media/file/update?id=$file->id");

        self::assertResponseIsSuccessful();
        self::assertAnySelectorTextNotContains('.dropdown-item .btn', 'Resubmit stream');
    }

    private function loginAdmin(): void
    {
        $user = $this->getUserFromFixture('admin');
        $this->assignAdminRole($user->id);
        $this->getWebUser()->login($user);
    }

    private function postResubmit(File $file): void
    {
        $request = $this->getWebRequest();

        self::$crawler = self::$client->request('POST', "/admin/media/stream/resubmit?id=$file->id", [
            $request->csrfParam => $request->getCsrfToken(),
        ]);
    }

    /**
     * @param array<string, mixed> $parameters
     */
    private function post(string $uri, array $parameters = []): void
    {
        self::$client = new Browser(['HTTP_HOST' => 'www.test.localhost', 'HTTPS' => 'on']);
        self::$crawler = self::$client->request('POST', $uri, $parameters);
    }

    private function renderMedia(File $file, bool $lazyLoading): string
    {
        return (string)Media::make()
            ->asset($this->createAsset($file))
            ->lazyLoading($lazyLoading);
    }

    private function createAsset(File $file): TestAsset
    {
        $asset = TestAsset::create();
        $asset->populateFileRelation($file);

        return $asset;
    }

    private function insertReadyVideo(): File
    {
        $file = $this->insertVideo();
        $manager = StreamManager::getComponent();
        $state = $manager->getState($file) ?? self::fail('No stream state');

        $manager->saveState($file, $state->withStatus(StreamStatus::Ready));

        return $file;
    }

    private function insertVideo(string $name = 'clip'): File
    {
        $file = File::create();
        $file->loadDefaultValues();
        $file->name = ucfirst($name);
        $file->populateFolderRelation($this->folder);

        $path = Yii::getAlias('@runtime') . "/$name.mp4";
        copy(__DIR__ . '/../Data/clip.mp4', $path);

        try {
            self::assertTrue($file->copy($path));
            self::assertTrue($file->insert(), print_r($file->getErrors(), true));
        } finally {
            FileHelper::unlink($path);
        }

        return $file;
    }

    private function getProvider(): FakeStreamProvider
    {
        $provider = StreamManager::getComponent()->getProvider();
        self::assertInstanceOf(FakeStreamProvider::class, $provider);

        return $provider;
    }
}
