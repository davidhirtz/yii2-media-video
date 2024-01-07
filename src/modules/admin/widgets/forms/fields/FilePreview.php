<?php

namespace davidhirtz\yii2\media\video\modules\admin\widgets\forms\fields;

use yii\helpers\Html;

class FilePreview extends \davidhirtz\yii2\media\modules\admin\widgets\forms\fields\FilePreview
{
    protected function renderField(): string
    {
        return $this->file->isVideo() ? $this->renderVideoTag() : parent::renderField();
    }

    protected function renderVideoTag(): string
    {
        return Html::tag('video', '', [
            'src' => $this->file->getUrl(),
            'class' => 'img-transparent',
            'style' => 'width:100%',
            'controls' => true,
        ]);
    }
}
