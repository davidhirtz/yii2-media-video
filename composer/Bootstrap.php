<?php

namespace davidhirtz\yii2\media\video\composer;

use davidhirtz\yii2\media\models\File;
use davidhirtz\yii2\media\video\models\behaviors\FileVideoBehavior;
use davidhirtz\yii2\skeleton\web\Application;
use yii\base\BootstrapInterface;
use yii\base\Event;

/**
 * Bootstrap attaches {@see FileVideoBehavior} on boot.
 */
class Bootstrap implements BootstrapInterface
{
    /**
     * @param Application $app
     */
    public function bootstrap($app)
    {
        Event::on(File::class, File::EVENT_INIT, function (Event $event) {
            /** @var File $file */
            $file = $event->sender;
            $file->attachBehavior('FileVideoBehavior', FileVideoBehavior::class);
        });
    }
}