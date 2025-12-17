<?php

declare(strict_types=1);

namespace Hirtz\Media\Video\Modules\Admin\Widgets\Grids\Columns;

use Hirtz\Media\Video\Assets\VideoAssetBundle;
use Hirtz\Skeleton\Helpers\Html;
use Yii;

class Thumbnail extends \Hirtz\Media\Modules\Admin\Widgets\Grids\Columns\Thumbnail
{
    protected function renderThumbnailContent(): string
    {
        if (!$this->file->isVideo()) {
            return parent::renderThumbnailContent();
        }

        $bundle = VideoAssetBundle::register(Yii::$app->getView());

        return Html::tag('div', '', [
            'class' => 'thumb bg-dark',
            'style' => [
                'background' => "url($bundle->baseUrl/video.svg) center no-repeat",
                'background-size' => '50%',
            ],
        ]);
    }
}
