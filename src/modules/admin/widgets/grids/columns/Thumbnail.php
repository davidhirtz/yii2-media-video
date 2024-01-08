<?php

namespace davidhirtz\yii2\media\video\modules\admin\widgets\grids\columns;

use davidhirtz\yii2\media\video\modules\admin\assets\VideoAsset;
use davidhirtz\yii2\skeleton\helpers\Html;
use Yii;

class Thumbnail extends \davidhirtz\yii2\media\modules\admin\widgets\grids\columns\Thumbnail
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
