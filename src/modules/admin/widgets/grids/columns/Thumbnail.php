<?php

namespace Hirtz\Media\video\modules\admin\widgets\grids\columns;

use Hirtz\Media\video\modules\admin\assets\VideoAsset;
use Hirtz\Skeleton\helpers\Html;
use Yii;

class Thumbnail extends \Hirtz\Media\modules\admin\widgets\grids\columns\Thumbnail
{
    protected function renderThumbnailContent(): string
    {
        if (!$this->file->isVideo()) {
            return parent::renderThumbnailContent();
        }

        $bundle = VideoAsset::register(Yii::$app->getView());

        return Html::tag('div', '', [
            'class' => 'thumb bg-dark',
            'style' => [
                'background' => "url($bundle->baseUrl/video.svg) center no-repeat",
                'background-size' => '50%',
            ],
        ]);
    }
}
