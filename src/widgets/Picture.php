<?php

namespace davidhirtz\yii2\media\video\widgets;

use davidhirtz\yii2\skeleton\helpers\Html;
use yii\helpers\ArrayHelper;

class Picture extends \davidhirtz\yii2\media\widgets\Picture
{
    public array $videoOptions = [];
    public ?bool $lazyVideoLoading = null;

    public function init(): void
    {
        $this->lazyVideoLoading ??= $this->defaultImageLoading === 'lazy';
        $this->prepareVideoOptions();

        parent::init();
    }

    public function render(): string
    {
        return $this->asset->file->isVideo() ? $this->getVideoTag() : parent::render();
    }

    public function getVideoTag(): string
    {
        return Html::tag('video', '', $this->videoOptions);
    }

    protected function prepareVideoOptions(): void
    {
        $autoplay = ArrayHelper::remove($this->videoOptions, 'autoplay', false);

        $this->videoOptions[$this->lazyVideoLoading ? 'data-src' : 'src'] ??= $this->asset->file->getUrl();
        $this->videoOptions['preload'] ??= $this->lazyVideoLoading ? 'none' : 'auto';

        $this->videoOptions['controls'] ??= !$autoplay;
        $this->videoOptions['playsinline'] ??= true;

        $this->videoOptions['autoplay'] ??= !$this->videoOptions['controls'];
        $this->videoOptions['loop'] ??= !$this->videoOptions['controls'];

        if (!$this->videoOptions['controls']) {
            $this->videoOptions['muted'] ??= '';
        }
    }
}
