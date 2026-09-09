<?php

declare(strict_types=1);

namespace Hirtz\Media\Video;

use Hirtz\Media\Models\File;
use Hirtz\Media\Module;
use Hirtz\Media\Video\Models\Behaviors\FileVideoBehavior;
use Hirtz\Media\Video\Modules\Admin\Widgets\Forms\Fields\FilePreviewField;
use Hirtz\Media\Video\Modules\Admin\Widgets\Grids\Columns\Thumbnail;
use Hirtz\Media\Video\Widgets\Media;
use Hirtz\Skeleton\Web\Application;
use Yii;
use yii\base\BootstrapInterface;
use yii\base\Event;

class Bootstrap implements BootstrapInterface
{
    /** @var string[] */
    public array $allowedVideoExtensions = ['mp4', 'webm', 'ogg'];

    /**
     * @param Application<\Hirtz\Skeleton\Models\User> $app
     */
    public function bootstrap($app): void
    {
        if (!isset($app->getModules()['media']['allowedExtensions'])) {
            Event::on(Module::class, Module::EVENT_INIT, function (Event $event): void {
                /** @var Module $module */
                $module = $event->sender;

                $module->allowedExtensions = [
                    ...$module->allowedExtensions,
                    ...$this->allowedVideoExtensions,
                ];
            });
        }

        Event::on(File::class, File::EVENT_INIT, function (Event $event): void {
            /** @var File $file */
            $file = $event->sender;
            $file->attachBehavior('FileVideoBehavior', FileVideoBehavior::class);
        });

        $definitions = [
            \Hirtz\Media\Modules\Admin\Widgets\Forms\Fields\FilePreviewField::class => FilePreviewField::class,
            \Hirtz\Media\Modules\Admin\Widgets\Grids\Columns\Thumbnail::class => Thumbnail::class,
            \Hirtz\Media\Widgets\Media::class => Media::class,
        ];

        foreach ($definitions as $class => $definition) {
            if (!Yii::$container->has($class)) {
                Yii::$container->set($class, $definition);
            }
        }
    }
}
