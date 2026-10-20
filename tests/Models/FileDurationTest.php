<?php

declare(strict_types=1);

namespace Hirtz\Media\Video\Tests\Models;

use Hirtz\Media\Models\File;
use Hirtz\Media\Modules\Admin\Widgets\Forms\FileActiveForm;
use Hirtz\Media\Test\TestCase;
use Hirtz\Media\Test\Traits\MediaFileTrait;
use Hirtz\Media\Video\Console\Controllers\FileController;
use Hirtz\Skeleton\Console\Application;
use Hirtz\Skeleton\Helpers\FileHelper;
use Hirtz\Skeleton\Mcp\Mcp;
use Hirtz\Skeleton\Mcp\Resource;
use Hirtz\Skeleton\Mcp\SchemaBuilder;
use Hirtz\Skeleton\Mcp\Tools\GetRecordTool;
use Hirtz\Skeleton\Test\Traits\StdOutBufferControllerTrait;
use Hirtz\Skeleton\Test\Traits\UserFixtureTrait;
use Override;
use Yii;

class FileDurationTest extends TestCase
{
    use MediaFileTrait;
    use UserFixtureTrait;

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

    public function testAnUploadedVideoStoresItsRoundedDuration(): void
    {
        $file = $this->insertVideo();

        self::assertSame(16, $file->width);
        self::assertSame(3, File::findOne($file->id)?->getAttribute('duration'));
        $definition = $file->getCustomAttribute('duration') ?? self::fail('No duration');

        self::assertSame('0:00:03', $definition->formatValue($file, 3));
        self::assertSame('1:01:05', $definition->formatValue($file, 3665));
    }

    public function testTheFormShowsTheDurationAsText(): void
    {
        $file = $this->insertVideo();
        $html = (string)FileActiveForm::make()->model($file);

        self::assertMatchesRegularExpression('#<div class="label">Duration</div>.*?<div class="form-text">0:00:03</div>#s', $html);
        self::assertStringNotContainsString('File[duration]', $html);

        preg_match('#<fieldset(?:(?!</fieldset>).)*?0:00:03.*?</fieldset>#s', $html, $fieldset);
        self::assertStringContainsString('id="file-size-row"', $fieldset[0] ?? self::fail('No fieldset holds the duration.'));

        $file->setAttribute('duration', null);
        self::assertStringNotContainsString('Duration', (string)FileActiveForm::make()->model($file));
    }

    public function testAnImageHasNoDuration(): void
    {
        $file = $this->createFile('pic');

        self::assertNull($file->getAttribute('duration'));
        self::assertNotContains('duration', $file->safeAttributes());
    }

    public function testAPostedDurationIsIgnored(): void
    {
        $file = $this->insertVideo();
        $file->load(['File' => ['duration' => '99', 'name' => 'Umbenannt']]);

        self::assertSame(1, $file->update(), print_r($file->getErrors(), true));
        self::assertSame(3, File::findOne($file->id)?->getAttribute('duration'));
    }

    public function testTheCommandFillsAVideoWithoutADuration(): void
    {
        $missing = $this->insertVideo('missing');
        $missing->setAttribute('duration', null);
        $missing->updateAttributes([$missing->getCustomAttributesColumn() => $missing->getSerializedCustomAttributesColumn()]);

        $kept = $this->insertVideo('kept');
        $kept->setAttribute('duration', 42);
        $kept->updateAttributes([$kept->getCustomAttributesColumn() => $kept->getSerializedCustomAttributesColumn()]);

        $controller = new TestVideoFileController('file', Yii::$app);
        $controller->actionDuration();

        self::assertStringContainsString('The duration of 1 videos was read', $controller->flushStdOutBuffer());
        self::assertSame(3, File::findOne($missing->id)?->getAttribute('duration'));
        self::assertSame(42, File::findOne($kept->id)?->getAttribute('duration'));
    }

    public function testTheConsoleFileControllerIsTheVideoOne(): void
    {
        $this->applicationClass = Application::class;
        $this->reloadApplication();

        self::assertInstanceOf(FileController::class, Yii::$app->createController('file')[0] ?? null);
    }

    public function testAgentsReadTheDuration(): void
    {
        $file = $this->insertVideo();
        $this->getWebUser()->setIdentity($this->getUserFromFixture('owner'));

        $result = (new GetRecordTool(Mcp::getComponent()))->call(['resource' => 'file', 'id' => $file->id]);
        self::assertSame(3, $result->structuredContent['attributes']['duration'] ?? null);

        $builder = new SchemaBuilder(Resource::make('file', File::class));
        self::assertContains('duration', $builder->getReadOnlyAttributes($file));
        self::assertNotContains('duration', $builder->getWritableAttributes($file));
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
}

class TestVideoFileController extends FileController
{
    use StdOutBufferControllerTrait;
}
