<?php

namespace davidhirtz\yii2\media\video\widgets\forms;

use davidhirtz\yii2\media\models\interfaces\AssetInterface;
use davidhirtz\yii2\media\models\File;
use yii\helpers\Html;

/**
 * @property File|AssetInterface $model
 * @noinspection PhpUnused
 */
trait FileVideoPreviewTrait
{
    public function previewField(): string
    {
        $file = $this->model instanceof File ? $this->model : $this->model->file;

        if ($file->isVideo()) {
            return $this->row($this->offset(Html::tag('video', '', [
                'src' => $file->getUrl(),
                'class' => 'img-transparent',
                'style' => 'width:100%',
                'controls' => true,
            ])));
        }

        return parent::previewField();
    }
}