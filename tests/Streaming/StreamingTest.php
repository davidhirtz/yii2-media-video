<?php

declare(strict_types=1);

namespace Hirtz\Media\Video\Tests\Streaming;

use Hirtz\Media\Models\Actions\DuplicateFile;
use Hirtz\Media\Models\File;
use Hirtz\Media\Modules\Admin\Widgets\Forms\FileActiveForm;
use Hirtz\Media\Test\TestCase;
use Hirtz\Media\Test\Traits\MediaFileTrait;
use Hirtz\Media\Video\Console\Controllers\VideoStreamController;
use Hirtz\Media\Video\Models\CustomAttributes\StreamCustomAttribute;
use Hirtz\Media\Video\Streaming\StreamManager;
use Hirtz\Media\Video\Streaming\StreamState;
use Hirtz\Media\Video\Streaming\StreamStatus;
use Hirtz\Media\Video\Test\FakeStreamProvider;
use Hirtz\Skeleton\Console\Application as ConsoleApplication;
use Hirtz\Skeleton\Helpers\ConfigFile;
use Hirtz\Skeleton\Helpers\FileHelper;
use Hirtz\Skeleton\Mcp\Mcp;
use Hirtz\Skeleton\Mcp\Resource;
use Hirtz\Skeleton\Mcp\SchemaBuilder;
use Hirtz\Skeleton\Mcp\Tools\GetRecordTool;
use Hirtz\Skeleton\Test\Traits\StdOutBufferControllerTrait;
use Hirtz\Skeleton\Test\Traits\UserFixtureTrait;
use Hirtz\Skeleton\Web\ContentSecurityPolicy;
use Override;
use Yii;
use yii\base\InlineAction;
use yii\console\ExitCode;

class StreamingTest extends TestCase
{
    use MediaFileTrait;
    use UserFixtureTrait;

    private const string PARAMS_DIRECTORY = '@runtime/video-stream-config';

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
        FileHelper::removeDirectory(Yii::getAlias(self::PARAMS_DIRECTORY));
        parent::tearDown();
    }

    public function testNothingIsWiredWithoutTheComponent(): void
    {
        unset($this->config['components']['videoStream']);
        $this->reloadApplication();

        $file = $this->insertVideo();

        self::assertFalse($file->hasAttribute(StreamManager::ATTRIBUTE));
        self::assertArrayNotHasKey('video-stream-webhook', Yii::$app->controllerMap);
        self::assertFalse(Yii::$app->getModule('admin')?->getModule('media')?->createController('stream'));
    }

    public function testAProviderNotSetUpWiresOnlyTheSetupCommand(): void
    {
        $this->config['components']['videoStream']['provider']['provisioned'] = false;
        $this->applicationClass = ConsoleApplication::class;
        $this->reloadApplication();

        $file = $this->insertVideo();

        self::assertFalse($file->hasAttribute(StreamManager::ATTRIBUTE));
        self::assertSame([], $this->getProvider()->calls);

        $controller = $this->createController();

        self::assertFalse($controller->beforeAction(new InlineAction('submit', $controller, 'actionSubmit')));
        self::assertTrue($controller->beforeAction(new InlineAction('setup', $controller, 'actionSetup')));
        self::assertTrue($controller->beforeAction(new InlineAction('index', $controller, 'actionIndex')));
    }

    public function testAnUploadedVideoIsSubmittedByUrl(): void
    {
        $file = $this->insertVideo();

        self::assertSame([[
            'submit',
            $file->id,
            'https://www.test.localhost' . $file->getUrl(),
            'https://www.test.localhost/video-stream/webhook',
        ]], $this->getProvider()->getCalls('submit'));

        $state = $this->getState(File::findOne($file->id));

        self::assertSame(StreamStatus::Processing, $state->status);
        self::assertSame('fake', $state->provider);
        self::assertSame($this->getState($file)->id, $state->id);
    }

    public function testAnImageIsNotSubmitted(): void
    {
        $this->createFile('pic');
        self::assertSame([], $this->getProvider()->calls);
    }

    public function testASaveWithoutAnUploadSubmitsNothing(): void
    {
        $file = $this->insertVideo();
        $file->name = 'Umbenannt';

        self::assertSame(1, $file->update());
        self::assertCount(1, $this->getProvider()->getCalls('submit'));
    }

    public function testAReplacedUploadDeletesTheOldRemoteVideo(): void
    {
        $file = $this->insertVideo();
        $old = $this->getState($file)->id;

        $this->replaceUpload($file);

        self::assertSame([['delete', $old]], $this->getProvider()->getCalls('delete'));
        self::assertCount(2, $this->getProvider()->getCalls('submit'));
        self::assertNotSame($old, $this->getState(File::findOne($file->id))->id);
    }

    public function testAFailedSubmitLeavesTheUploadSaved(): void
    {
        $this->getProvider()->submitStatus = StreamStatus::Failed;
        $this->getProvider()->error = 'Квота überschritten 🎬';

        $file = $this->insertVideo();
        $state = $this->getState(File::findOne($file->id));

        self::assertFileExists($file->getFilePath());
        self::assertSame(StreamStatus::Failed, $state->status);

        $definition = $file->getCustomAttribute(StreamManager::ATTRIBUTE) ?? self::fail('No stream attribute');
        self::assertSame('Failed: Квота überschritten 🎬', $definition->formatValue($file, $state));
        self::assertSame('Failed: Квота überschritten 🎬', $definition->formatValue($file, $state->toArray()));
    }

    public function testWithoutSubmitByUrlTheConsoleUploadsTheBytes(): void
    {
        StreamManager::getComponent()->submitByUrl = false;

        $file = $this->insertVideo();
        $state = $this->getState(File::findOne($file->id));

        self::assertNull($state->id);
        self::assertSame(StreamStatus::Pending, $state->status);
        self::assertSame([], $this->getProvider()->getCalls('submit'));

        $controller = $this->createController();
        self::assertSame(ExitCode::OK, $controller->actionSubmit());

        self::assertSame([['submit', $file->id, null, 'https://www.test.localhost/video-stream/webhook']], $this->getProvider()->getCalls('submit'));
        self::assertSame(StreamStatus::Processing, $this->getState(File::findOne($file->id))->status);
        self::assertStringContainsString('1 videos were submitted', $controller->flushStdOutBuffer());
    }

    public function testTheConsoleSubmitsVideosWithoutAStreamOnlyWithAll(): void
    {
        $file = $this->insertVideo();
        $this->getManager()->saveState($file, null);

        $controller = $this->createController();
        $controller->actionSubmit();
        self::assertCount(1, $this->getProvider()->getCalls('submit'));

        $controller->all = true;
        $controller->actionSubmit();
        self::assertCount(2, $this->getProvider()->getCalls('submit'));

        $controller->actionSubmit();
        self::assertCount(2, $this->getProvider()->getCalls('submit'), 'A submitted video is not submitted again.');

        $controller->all = false;
        $controller->fileId = $file->id;
        $controller->actionSubmit();
        self::assertCount(3, $this->getProvider()->getCalls('submit'));
        self::assertCount(1, $this->getProvider()->getCalls('delete'));
    }

    public function testABusyProviderStopsTheRunAndLeavesTheVideoPending(): void
    {
        $first = $this->insertVideo('first');
        $second = $this->insertVideo('second');
        $this->getManager()->saveState($first, null);
        $this->getManager()->saveState($second, null);
        $this->getProvider()->calls = [];
        $this->getProvider()->submitStatus = StreamStatus::Pending;

        $controller = $this->createController();
        $controller->all = true;

        self::assertSame(ExitCode::TEMPFAIL, $controller->actionSubmit());
        self::assertCount(1, $this->getProvider()->getCalls('submit'), 'Every further submit would be refused as well.');

        $pending = array_values(array_filter(
            [$this->findState($first), $this->findState($second)],
            static fn (?StreamState $state): bool => $state !== null,
        ));

        self::assertCount(1, $pending);
        $state = $pending[0] ?? self::fail('No video was left pending.');

        self::assertSame(StreamStatus::Pending, $state->status);
        self::assertNull($state->id);

        $this->getProvider()->submitStatus = StreamStatus::Processing;
        self::assertSame(ExitCode::OK, $controller->actionSubmit());

        self::assertCount(3, $this->getProvider()->getCalls('submit'));
        self::assertSame(StreamStatus::Processing, $this->findState($first)?->status);
        self::assertSame(StreamStatus::Processing, $this->findState($second)?->status);
    }

    public function testTheConsoleRefreshesOnlyStreamsInProgress(): void
    {
        $processing = $this->insertVideo('processing');
        $ready = $this->insertVideo('ready');
        $this->getManager()->saveState($ready, $this->getState($ready)->withStatus(StreamStatus::Ready));

        $this->getProvider()->status = StreamStatus::Ready;

        $controller = $this->createController();
        $controller->actionRefresh();

        self::assertSame([['fetchState', $this->getState($processing)->id]], $this->getProvider()->getCalls('fetchState'));
        self::assertSame(StreamStatus::Ready, $this->getState(File::findOne($processing->id))->status);
        self::assertStringContainsString('The state of 1 videos changed', $controller->flushStdOutBuffer());
    }

    public function testTheWebhookCommandRegistersTheEndpoint(): void
    {
        $controller = $this->createController();

        self::assertSame(ExitCode::OK, $controller->actionWebhook());
        self::assertSame([['registerWebhook', 'https://www.test.localhost/video-stream/webhook']], $this->getProvider()->getCalls('registerWebhook'));

        $this->getManager()->webhookEnabled = false;
        self::assertSame(ExitCode::CONFIG, $controller->actionWebhook());
    }

    public function testTheSetupCommandSavesTheParams(): void
    {
        $config = $this->createParamsFile(['cookieValidationKey' => 'Grüße 🎬']);
        $this->getProvider()->provisioned = false;

        $controller = $this->createController();
        $controller->config = $config;
        self::assertSame(ExitCode::USAGE, $controller->actionSetup());

        $controller->accountKey = 'account-key';
        $controller->name = 'Grüße 🎬';

        self::assertSame(ExitCode::OK, $controller->actionSetup());
        self::assertSame([['provision', 'account-key', 'Grüße 🎬', 'https://www.test.localhost/video-stream/webhook']], $this->getProvider()->getCalls('provision'));
        self::assertSame(['cookieValidationKey' => 'Grüße 🎬', 'fakeApiKey' => 'key', 'fakeLibraryId' => '1'], require Yii::getAlias($config));
        self::assertSame('1', Yii::$app->params['fakeLibraryId']);
    }

    public function testTheSetupCommandReadsTheAccountKeyFromTheEnvironmentOrAsksForIt(): void
    {
        $this->getProvider()->provisioned = false;

        $controller = $this->createController();
        $controller->config = $this->createParamsFile([]);
        $controller->secret = 'Schlüssel-🔑';

        self::assertSame(ExitCode::OK, $controller->actionSetup());
        self::assertSame('Schlüssel-🔑', $this->getProvider()->getCalls('provision')[0][1] ?? null);

        putenv('VIDEO_STREAM_ACCOUNT_KEY=environment-key');

        try {
            $controller = $this->createController();
            $controller->config = $this->createParamsFile([]);
            $controller->interactive = false;

            self::assertSame(ExitCode::OK, $controller->actionSetup());
            self::assertSame('environment-key', $this->getProvider()->getCalls('provision')[1][1] ?? null);
        } finally {
            putenv('VIDEO_STREAM_ACCOUNT_KEY');
        }
    }

    public function testTheSetupCommandNeverReplacesAScopeUnattended(): void
    {
        $config = $this->createParamsFile(['fakeLibraryId' => '7']);
        $this->getProvider()->provisioned = true;

        $controller = $this->createController();
        $controller->config = $config;
        $controller->accountKey = 'account-key';
        $controller->interactive = false;

        self::assertSame(ExitCode::OK, $controller->actionSetup());
        self::assertSame([], $this->getProvider()->getCalls('provision'));
        self::assertSame(['fakeLibraryId' => '7'], require Yii::getAlias($config));
    }

    public function testASubmittedStateCarriesTheProvidersScope(): void
    {
        self::assertSame('fake-library', $this->getState(File::findOne($this->insertVideo()->id))->scope);

        StreamManager::getComponent()->submitByUrl = false;
        self::assertSame('fake-library', $this->getState(File::findOne($this->insertVideo('pending')->id))->scope);
    }

    public function testAStateCopiedFromAnotherInstallationIsNeitherPlayedNorFetchedNorDeleted(): void
    {
        $file = $this->insertCopiedVideo();
        $manager = $this->getManager();

        self::assertTrue($manager->isForeignState($this->getState($file)));
        self::assertFalse($manager->isPlayable($file));
        self::assertNull($manager->getPlaylistUrl($file));
        self::assertNull($manager->getPosterUrl($file));

        $manager->refresh($file);
        $manager->deleteRemote($file);

        self::assertSame([], $this->getProvider()->getCalls('fetchState'));
        self::assertSame([], $this->getProvider()->getCalls('delete'));
        self::assertNull($manager->findFile((string)$this->getState($file)->id));
        self::assertSame(Yii::t('video', 'FILE_STREAM_STATUS_FOREIGN_LABEL'), StreamCustomAttribute::make(StreamManager::ATTRIBUTE)->formatValue($file, $this->getState($file)));
    }

    public function testSubmitAllStreamsTheVideosOfACopiedInstallationAgain(): void
    {
        $file = $this->insertCopiedVideo();
        $copied = $this->getState($file)->id;

        $controller = $this->createController();
        $controller->actionSubmit();
        self::assertSame([], $this->getProvider()->getCalls('submit'));

        $controller->all = true;
        $controller->actionSubmit();

        $state = $this->getState(File::findOne($file->id));

        self::assertCount(1, $this->getProvider()->getCalls('submit'));
        self::assertSame([], $this->getProvider()->getCalls('delete'), 'The copied video belongs to the other library.');
        self::assertNotSame($copied, $state->id);
        self::assertSame('production-library', $state->scope);
        self::assertFalse($this->getManager()->isForeignState($state));
    }

    public function testTheSetupCommandNamesTheVideosOfACopiedInstallation(): void
    {
        $this->insertCopiedVideo('Grüße 🎬');
        $this->getProvider()->provisioned = false;

        $controller = $this->createController();
        $controller->config = $this->createParamsFile([]);
        $controller->accountKey = 'account-key';

        self::assertSame(ExitCode::OK, $controller->actionSetup());
        self::assertStringContainsString('1 videos were streamed by another installation, run `video-stream/submit --all`', $controller->flushStdOutBuffer());
    }

    /**
     * Ready in the stage library, then seen from production, whose library is another.
     */
    private function insertCopiedVideo(string $name = 'copied'): File
    {
        $file = $this->insertVideo($name);
        $this->getManager()->saveState($file, $this->getState($file)->withStatus(StreamStatus::Ready));

        $this->getProvider()->scope = 'production-library';
        $this->getProvider()->calls = [];

        return $file;
    }

    /**
     * @param array<string, mixed> $params
     */
    private function createParamsFile(array $params): string
    {
        $file = self::PARAMS_DIRECTORY . '/params.php';
        self::assertTrue(ConfigFile::write($file, $params));

        return $file;
    }

    public function testTheConsoleControllerIsRegistered(): void
    {
        $this->applicationClass = ConsoleApplication::class;
        $this->reloadApplication();

        self::assertInstanceOf(VideoStreamController::class, Yii::$app->createController('video-stream')[0] ?? null);
    }

    public function testASubmitThatCannotBuildItsUrlsKeepsThePlayableStream(): void
    {
        $this->applicationClass = ConsoleApplication::class;
        $this->config['components']['videoStream']['submitByUrl'] = false;
        $this->reloadApplication();

        $file = $this->insertVideo();
        $ready = new StreamState('fake', 'fake-ready', StreamStatus::Ready, scope: 'fake-library');
        $this->getManager()->saveState($file, $ready);
        $this->getManager()->submitByUrl = true;
        $this->getProvider()->calls = [];

        $controller = $this->createController();
        $controller->fileId = $file->id;

        self::assertSame(ExitCode::UNSPECIFIED_ERROR, $controller->actionSubmit());
        self::assertSame([], $this->getProvider()->calls, 'Nothing is deleted or submitted.');
        self::assertSame($ready->toArray(), $this->findState($file)?->toArray());
    }

    public function testAnEditorsSaveKeepsTheStateAWebhookWroteMeanwhile(): void
    {
        $file = $this->insertVideo();
        $form = $this->reload($file);

        $ready = $this->getState($file)->withStatus(StreamStatus::Ready);
        $this->getManager()->saveState($file, $ready);

        $form->name = 'Grüße 🎬';
        self::assertSame(1, $form->update());

        self::assertSame($ready->toArray(), $this->findState($file)?->toArray());
    }

    public function testAWebhookKeepsWhatAnEditorSavedMeanwhile(): void
    {
        $file = $this->insertVideo();
        $webhook = $this->reload($file);

        $form = $this->reload($file);
        $form->setAttribute('duration', 42);
        self::assertSame(1, $form->update());

        $this->getProvider()->status = StreamStatus::Failed;
        $this->getProvider()->error = 'Ошибка 🎬 Mu' . "\u{308}" . 'ller';
        $this->getManager()->refresh($webhook);

        $stored = $this->reload($file);

        self::assertSame(42, $stored->getAttribute('duration'));
        self::assertSame('Ошибка 🎬 Mu' . "\u{308}" . 'ller', $this->getManager()->getState($stored)?->error);
    }

    public function testARefreshNeverWritesAnOlderStateOverANewerOne(): void
    {
        $file = $this->insertVideo();
        $late = $this->reload($file);

        $this->getManager()->saveState($file, $this->getState($file)->withStatus(StreamStatus::Ready));
        $this->getProvider()->status = StreamStatus::Processing;

        self::assertSame(StreamStatus::Ready, $this->getManager()->refresh($late)?->status);
        self::assertSame(StreamStatus::Ready, $this->findState($file)?->status);

        $resubmitted = $this->getManager()->submit($file);
        $this->getManager()->refresh($late);

        self::assertSame($resubmitted->id, $this->findState($file)->id ?? null, 'A refresh of the replaced video is dropped.');
    }

    /**
     * `setup` and `webhook` register what the console builds, so it must be the URL the web application answers.
     */
    public function testTheConsoleBuildsTheWebhookUrlOfTheWebRule(): void
    {
        $this->applicationClass = ConsoleApplication::class;
        $this->config['components']['urlManager']['hostInfo'] = 'https://www.test.localhost';
        $this->config['components']['urlManager']['baseUrl'] = '';
        $this->reloadApplication();

        self::assertSame('https://www.test.localhost/video-stream/webhook', $this->getManager()->getWebhookUrl());
    }

    public function testDeletingTheFileDeletesTheRemoteVideo(): void
    {
        $file = $this->insertVideo();
        $id = $this->getState($file)->id;

        self::assertSame(1, $file->delete());
        self::assertSame([['delete', $id]], $this->getProvider()->getCalls('delete'));
    }

    public function testADuplicateGetsARemoteVideoOfItsOwn(): void
    {
        $file = $this->insertVideo();
        $duplicate = DuplicateFile::create(['file' => $file]);

        self::assertFalse($duplicate->hasErrors(), print_r($duplicate->getErrors(), true));
        self::assertNotSame($this->getState($file)->id, $this->getState(File::findOne($duplicate->id))->id);
        self::assertSame([], $this->getProvider()->getCalls('delete'));
    }

    public function testAnUploadTurningTheFileIntoAnImageDeletesTheStream(): void
    {
        $file = $this->insertVideo();
        $id = $this->getState($file)->id;

        $path = Yii::getAlias('@runtime') . '/still.jpg';
        $this->writeImage($path);

        try {
            self::assertTrue($file->copy($path));
            self::assertSame(1, $file->update(), print_r($file->getErrors(), true));
        } finally {
            FileHelper::unlink($path);
        }

        self::assertSame([['delete', $id]], $this->getProvider()->getCalls('delete'));
        self::assertNull(File::findOne($file->id)?->getAttribute(StreamManager::ATTRIBUTE));
    }

    public function testAPostedStateIsIgnored(): void
    {
        $file = $this->insertVideo();
        $state = $this->getState($file);

        $file->load(['File' => [StreamManager::ATTRIBUTE => ['provider' => 'fake', 'id' => 'forged', 'status' => 'ready']]]);

        self::assertSame($state, $file->getAttribute(StreamManager::ATTRIBUTE));
        self::assertNotContains(StreamManager::ATTRIBUTE, $file->safeAttributes());
    }

    public function testAMalformedStoredStateReadsAsNone(): void
    {
        $file = $this->insertVideo();
        $file->updateAttributes([$file->getCustomAttributesColumn() => [StreamManager::ATTRIBUTE => ['status' => 'done']]]);

        self::assertNull(File::findOne($file->id)?->getAttribute(StreamManager::ATTRIBUTE));
    }

    public function testRefreshSavesAChangedStateAndATrail(): void
    {
        $file = $this->insertVideo();
        $this->getProvider()->status = StreamStatus::Ready;

        $state = $this->getManager()->refresh($file);

        self::assertSame(StreamStatus::Ready, $state?->status);
        self::assertNotNull($this->getState(File::findOne($file->id))->readyAt);
        self::assertTrue($this->getManager()->isPlayable($file));
    }

    public function testAStateOfAnotherProviderIsLeftAlone(): void
    {
        $file = $this->insertVideo();
        $this->getManager()->saveState($file, new StreamState('other', 'abc', StreamStatus::Ready));

        $this->getManager()->refresh($file);
        $this->getManager()->deleteRemote($file);

        self::assertSame([], $this->getProvider()->getCalls('fetchState'));
        self::assertSame([], $this->getProvider()->getCalls('delete'));
        self::assertFalse($this->getManager()->isPlayable($file));
    }

    public function testAFileIsFoundByItsRemoteId(): void
    {
        $file = $this->insertVideo();
        $id = (string)$this->getState($file)->id;

        self::assertSame($file->id, $this->getManager()->findFile($id)?->id);
        self::assertNull($this->getManager()->findFile('unknown'));
        self::assertNull($this->getManager()->findFile('"' . $id));
    }

    public function testTheFormShowsTheStatusAsText(): void
    {
        $file = $this->insertVideo();
        $html = (string)FileActiveForm::make()->model($file);

        self::assertMatchesRegularExpression('#<div class="label">Stream</div>.*?<div class="form-text">Processing</div>#s', $html);
    }

    public function testAgentsReadTheStateButCannotWriteIt(): void
    {
        $file = $this->insertVideo();
        $this->getWebUser()->setIdentity($this->getUserFromFixture('owner'));

        $result = (new GetRecordTool(Mcp::getComponent()))->call(['resource' => 'file', 'id' => $file->id]);
        self::assertSame('processing', $result->structuredContent['attributes']['stream'] ?? null);

        $builder = new SchemaBuilder(Resource::make('file', File::class));
        self::assertContains(StreamManager::ATTRIBUTE, $builder->getReadOnlyAttributes($file));
        self::assertNotContains(StreamManager::ATTRIBUTE, $builder->getWritableAttributes($file));
    }

    public function testTheContentSecurityPolicyIsOnlyWidenedWhereItRestricts(): void
    {
        $policy = new ContentSecurityPolicy();
        $this->getManager()->registerContentSecurityPolicy($policy);

        self::assertArrayNotHasKey('media-src', $policy->directives);

        $policy->setDirective('default-src', ["'self'"]);
        $policy->setDirective('img-src', ["'self'", 'data:']);
        $this->getManager()->registerContentSecurityPolicy($policy);

        self::assertSame(["'self'", 'https://stream.example.com', 'blob:'], $policy->directives['media-src']);
        self::assertSame(["'self'", 'https://stream.example.com'], $policy->directives['connect-src']);
        self::assertSame(["'self'", 'data:', 'https://stream.example.com'], $policy->directives['img-src']);
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

    private function replaceUpload(File $file): void
    {
        $path = Yii::getAlias('@runtime') . '/replacement.mp4';
        copy(__DIR__ . '/../Data/clip.mp4', $path);

        try {
            self::assertTrue($file->copy($path));
            self::assertSame(1, $file->update(), print_r($file->getErrors(), true));
        } finally {
            FileHelper::unlink($path);
        }
    }

    private function findState(File $file): ?StreamState
    {
        return $this->getManager()->getState($this->reload($file));
    }

    private function reload(File $file): File
    {
        return File::findOne($file->id) ?? self::fail('The file is gone.');
    }

    private function getState(?File $file): StreamState
    {
        return $this->getManager()->getState($file ?? self::fail('No file')) ?? self::fail('No stream state');
    }

    private function getManager(): StreamManager
    {
        return StreamManager::getComponent();
    }

    private function getProvider(): FakeStreamProvider
    {
        $provider = $this->getManager()->getProvider();
        self::assertInstanceOf(FakeStreamProvider::class, $provider);

        return $provider;
    }

    private function createController(): TestVideoStreamController
    {
        return new TestVideoStreamController('video-stream', Yii::$app);
    }
}

class TestVideoStreamController extends VideoStreamController
{
    use StdOutBufferControllerTrait;

    public ?string $secret = null;

    #[Override]
    protected function promptSecret(string $label): ?string
    {
        $this->stdout("$label ");
        return $this->secret;
    }
}
