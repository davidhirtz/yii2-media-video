<?php

namespace davidhirtz\yii2\media\video;

use davidhirtz\yii2\media\models\File;
use davidhirtz\yii2\media\video\models\behaviors\FileVideoBehavior;
use davidhirtz\yii2\media\video\modules\admin\widgets\forms\fields\FilePreview;
use davidhirtz\yii2\media\video\modules\admin\widgets\grids\columns\FileThumbnailColumn;
use davidhirtz\yii2\media\video\widgets\Picture;
use davidhirtz\yii2\skeleton\web\Application;
use Yii;
use yii\base\BootstrapInterface;
use yii\base\Event;

class Bootstrap implements BootstrapInterface
{
    /**
     * @param Application $app
     */
    public function bootstrap($app): void
    {
        Event::on(File::class, File::EVENT_INIT, function (Event $event) {
            /** @var File $file */
            $file = $event->sender;
            $file->attachBehavior('FileVideoBehavior', FileVideoBehavior::class);
        });

        $definitions = [
            \davidhirtz\yii2\media\modules\admin\widgets\forms\fields\FilePreview::class => FilePreview::class,
            \davidhirtz\yii2\media\modules\admin\widgets\grids\columns\FileThumbnailColumn::class => FileThumbnailColumn::class,
            \davidhirtz\yii2\media\widgets\Picture::class => Picture::class,
        ];

        foreach ($definitions as $class => $definition) {
            if (!Yii::$container->has($class)) {
                Yii::$container->set($class, $definition);
            }
        }
    }
}
