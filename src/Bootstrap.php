<?php

namespace Hirtz\Media\video;

use Hirtz\Media\models\File;
use Hirtz\Media\Module;
use Hirtz\Media\video\models\behaviors\FileVideoBehavior;
use Hirtz\Media\video\modules\admin\widgets\forms\fields\FilePreview;
use Hirtz\Media\video\modules\admin\widgets\grids\columns\Thumbnail;
use Hirtz\Media\video\widgets\Picture;
use Hirtz\Skeleton\web\Application;
use Yii;
use yii\base\BootstrapInterface;
use yii\base\Event;

class Bootstrap implements BootstrapInterface
{
    public array $allowedVideoExtensions = ['mp4', 'webm', 'ogg'];

    /**
     * @param Application $app
     */
    public function bootstrap($app): void
    {
        if (!isset($app->getModules()['media']['allowedExtensions'])) {
            Event::on(Module::class, Module::EVENT_INIT, function (Event $event) {
                /** @var Module $module */
                $module = $event->sender;

                $module->allowedExtensions = [
                    ...$module->allowedExtensions,
                    ...$this->allowedVideoExtensions,
                ];
            });
        }

        Event::on(File::class, File::EVENT_INIT, function (Event $event) {
            /** @var File $file */
            $file = $event->sender;
            $file->attachBehavior('FileVideoBehavior', FileVideoBehavior::class);
        });

        $definitions = [
            \Hirtz\Media\modules\admin\widgets\forms\fields\FilePreview::class => FilePreview::class,
            \Hirtz\Media\modules\admin\widgets\grids\columns\Thumbnail::class => Thumbnail::class,
            \Hirtz\Media\widgets\Picture::class => Picture::class,
        ];

        foreach ($definitions as $class => $definition) {
            if (!Yii::$container->has($class)) {
                Yii::$container->set($class, $definition);
            }
        }
    }
}
