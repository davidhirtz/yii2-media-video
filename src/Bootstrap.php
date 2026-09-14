<?php

declare(strict_types=1);

namespace Hirtz\Media\Video;

use Hirtz\Media\Models\File;
use Hirtz\Media\Module;
use Hirtz\Media\Video\Models\Behaviors\FileVideoBehavior;
use Hirtz\Media\Video\Modules\Admin\Widgets\Forms\Fields\FilePreviewField;
use Hirtz\Media\Video\Modules\Admin\Widgets\Grids\Columns\Thumbnail;
use Hirtz\Media\Video\Widgets\Media;
use Hirtz\Skeleton\Base\Module as BaseModule;
use Hirtz\Skeleton\Helpers\EventHelper;
use Hirtz\Skeleton\Web\Application;
use Yii;
use yii\base\BootstrapInterface;
use yii\db\BaseActiveRecord;

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
            EventHelper::on(Module::class, BaseModule::EVENT_INIT, function (Module $module): void {
                $module->allowedExtensions = [
                    ...$module->allowedExtensions,
                    ...$this->allowedVideoExtensions,
                ];
            });
        }

        EventHelper::on(
            File::class,
            BaseActiveRecord::EVENT_INIT,
            static fn (File $file) => $file->attachBehavior('FileVideoBehavior', FileVideoBehavior::class)
        );

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
