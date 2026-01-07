<?php

declare(strict_types=1);

namespace Hirtz\Media\Video\Modules\Admin\Widgets\Forms\Fields;

use Override;
use Stringable;
use yii\helpers\Html;

class FilePreviewField extends \Hirtz\Media\Modules\Admin\Widgets\Forms\Fields\FilePreviewField
{
    #[Override]
    protected function getContent(): string|Stringable
    {
        return $this->file->isVideo() ? $this->renderVideoTag() : parent::getContent();
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
