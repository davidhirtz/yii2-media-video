<?php

namespace Hirtz\Media\video\modules\admin\widgets\forms\fields;

use yii\helpers\Html;

class FilePreview extends \Hirtz\Media\modules\admin\widgets\forms\fields\FilePreview
{
    public function run(): string
    {
        return $this->file->isVideo() ? $this->renderVideoTag() : parent::run();
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
