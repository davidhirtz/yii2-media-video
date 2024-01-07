<?php

namespace davidhirtz\yii2\media\video\modules\admin\widgets\grids\columns;

use davidhirtz\yii2\media\models\File;
use davidhirtz\yii2\media\video\modules\admin\assets\VideoAsset;
use davidhirtz\yii2\skeleton\helpers\Html;

class FileThumbnailColumn extends \davidhirtz\yii2\media\modules\admin\widgets\grids\columns\FileThumbnailColumn
{
    protected function renderThumbnailContent(File $file): string
    {
        if (!$file->isVideo()) {
            return parent::renderThumbnailContent($file);
        }

        $bundle = VideoAsset::register($this->grid->getView());

        return Html::tag('div', '', [
            'class' => 'thumb bg-dark',
            'style' => [
                'background' => "url($bundle->baseUrl/video.svg) center no-repeat",
                'background-size' => '50%',
            ],
        ]);
    }
}
