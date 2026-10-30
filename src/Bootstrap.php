<?php

declare(strict_types=1);

namespace Hirtz\Media\Video;

use Hirtz\Media\Models\File;
use Hirtz\Media\Module;
use Hirtz\Media\Video\Assets\VideoPreviewAssetBundle;
use Hirtz\Media\Video\Console\Controllers\FileController;
use Hirtz\Media\Video\Console\Controllers\VideoStreamController;
use Hirtz\Media\Video\Controllers\StreamWebhookController;
use Hirtz\Media\Video\Mcp\GetSubtitlesTool;
use Hirtz\Media\Video\Mcp\SetSubtitlesTool;
use Hirtz\Media\Video\Models\Behaviors\FileStreamBehavior;
use Hirtz\Media\Video\Models\Behaviors\FileVideoBehavior;
use Hirtz\Media\Video\Models\CustomAttributes\DurationCustomAttribute;
use Hirtz\Media\Video\Models\CustomAttributes\StreamCustomAttribute;
use Hirtz\Media\Video\Models\CustomAttributes\SubtitlesCustomAttribute;
use Hirtz\Media\Video\Modules\Admin\Controllers\StreamController;
use Hirtz\Media\Video\Modules\Admin\Widgets\Forms\Fields\FilePreviewField;
use Hirtz\Media\Video\Modules\Admin\Widgets\Navs\FileActionDropdown;
use Hirtz\Media\Video\Modules\Admin\Widgets\Grids\Columns\Thumbnail;
use Hirtz\Media\Video\Streaming\StreamManager;
use Hirtz\Media\Video\Widgets\Media;
use Hirtz\Skeleton\Base\ConfigBootstrapInterface;
use Hirtz\Skeleton\Base\Module as BaseModule;
use Hirtz\Skeleton\Console\Application as ConsoleApplication;
use Hirtz\Skeleton\Helpers\EventHelper;
use Hirtz\Skeleton\Mcp\Mcp;
use Hirtz\Skeleton\Web\Application;
use Hirtz\Skeleton\Widgets\Buttons\FileUploadButton;
use Hirtz\Skeleton\Widgets\Widget;
use Override;
use Yii;
use yii\db\BaseActiveRecord;
use yii\i18n\PhpMessageSource;

class Bootstrap implements ConfigBootstrapInterface
{
    #[Override]
    public static function getDefaultConfig(): array
    {
        return [
            'components' => [
                'i18n' => [
                    'translations' => [
                        'video' => [
                            'class' => PhpMessageSource::class,
                            'basePath' => '@media-video/../messages',
                            'forceTranslation' => true,
                        ],
                    ],
                ],
                'mcp' => [
                    'tools' => [
                        static fn (Mcp $mcp): GetSubtitlesTool => new GetSubtitlesTool($mcp),
                        static fn (Mcp $mcp): SetSubtitlesTool => new SetSubtitlesTool($mcp),
                    ],
                ],
            ],
            'modules' => [
                'media' => [
                    'fileCustomAttributes' => [
                        static fn (): array => [
                            SubtitlesCustomAttribute::make('subtitles'),
                            DurationCustomAttribute::make('duration'),
                        ],
                    ],
                ],
            ],
            'container' => [
                'definitions' => [
                    \Hirtz\Media\Console\Controllers\FileController::class => FileController::class,
                    \Hirtz\Media\Modules\Admin\Widgets\Forms\Fields\FilePreviewField::class => FilePreviewField::class,
                    \Hirtz\Media\Modules\Admin\Widgets\Grids\Columns\Thumbnail::class => Thumbnail::class,
                    \Hirtz\Media\Modules\Admin\Widgets\Navs\FileActionDropdown::class => FileActionDropdown::class,
                    \Hirtz\Media\Widgets\Media::class => Media::class,
                ],
            ],
        ];
    }

    /** @var string[] */
    public array $allowedVideoExtensions = ['mp4', 'webm', 'ogg'];

    /**
     * @param Application<\Hirtz\Skeleton\Models\User>|ConsoleApplication $app
     */
    public function bootstrap($app): void
    {
        Yii::setAlias('@media-video', __DIR__);

        $config = $app->getModules()['media'] ?? [];
        $isAllowedExtensionsConfigured = isset($config['allowedExtensions']);
        $isVideoExtensionsConfigured = isset($config['videoExtensions']);

        EventHelper::on(Module::class, BaseModule::EVENT_INIT, function (Module $module) use ($isAllowedExtensionsConfigured, $isVideoExtensionsConfigured): void {
            if (!$isVideoExtensionsConfigured) {
                $module->videoExtensions = array_values($this->allowedVideoExtensions);
            }

            if (!$isAllowedExtensionsConfigured) {
                $module->allowedExtensions = [
                    ...$module->allowedExtensions,
                    ...$this->allowedVideoExtensions,
                ];
            }
        });

        // Registered with every upload button, since a grid without a video yet is where the first one is uploaded.
        EventHelper::on(
            FileUploadButton::class,
            Widget::EVENT_CONFIGURE,
            static fn () => VideoPreviewAssetBundle::register(Application::current()->getView())
        );

        EventHelper::on(
            File::class,
            BaseActiveRecord::EVENT_INIT,
            static fn (File $file) => $file->attachBehavior('FileVideoBehavior', FileVideoBehavior::class)
        );

        if ($app->has('videoStream')) {
            $this->bootstrapStreaming($app);
        }
    }

    /**
     * @param Application<\Hirtz\Skeleton\Models\User>|ConsoleApplication $app
     */
    protected function bootstrapStreaming(Application|ConsoleApplication $app): void
    {
        // Not `getIsConsoleRequest()`, which a web application under the CLI SAPI answers `true` as well
        if ($app instanceof ConsoleApplication) {
            $app->controllerMap['video-stream'] ??= VideoStreamController::class;
        }

        // The console builds the webhook URL `setup` registers, before the provider is set up.
        $app->addUrlManagerRules(['video-stream/webhook' => ltrim(StreamManager::WEBHOOK_ROUTE, '/')], true);

        // A provider bundle configures the component before its scope exists, so `video-stream/setup` can create it.
        if (!StreamManager::getComponent()->isSetUp()) {
            return;
        }

        EventHelper::on(Module::class, BaseModule::EVENT_INIT, static function (Module $module): void {
            $module->fileCustomAttributes[] = static fn (): array => [
                StreamCustomAttribute::make(StreamManager::ATTRIBUTE),
            ];
        });

        EventHelper::on(
            File::class,
            BaseActiveRecord::EVENT_INIT,
            static fn (File $file) => $file->attachBehavior('FileStreamBehavior', FileStreamBehavior::class)
        );

        if ($app instanceof ConsoleApplication) {
            return;
        }

        /** @see StreamWebhookController::actionIndex() */
        $app->controllerMap['video-stream-webhook'] ??= StreamWebhookController::class;

        $app->extendModule('admin', [
            'modules' => [
                'media' => [
                    'controllerMap' => [
                        'stream' => StreamController::class,
                    ],
                ],
            ],
        ]);

        $app->on(Application::EVENT_BEFORE_REQUEST, static function (): void {
            StreamManager::getComponent()->registerContentSecurityPolicy(Application::current()->getContentSecurityPolicy());
        });
    }
}
