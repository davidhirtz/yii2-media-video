<?php

namespace Hirtz\Media\video\Modules\Admin\Widgets\Forms\Fields;

use yii\helpers\Html;

class FilePreview extends \Hirtz\Media\Modules\Admin\Widgets\Forms\Fields\FilePreview
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
