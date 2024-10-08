<?php

namespace davidhirtz\yii2\media\video\widgets;

use davidhirtz\yii2\skeleton\helpers\Html;
use yii\helpers\ArrayHelper;

class Picture extends \davidhirtz\yii2\media\widgets\Picture
{
    public array $videoOptions = [];

    public function init(): void
    {
        $this->prepareVideoOptions();
        $this->addCssClass();

        parent::init();
    }

    public function run(): string
    {
        return $this->asset->file->isVideo() ? $this->getVideoTag() : parent::run();
    }

    public function getVideoTag(): string
    {
        return Html::tag('video', '', $this->videoOptions);
    }

    protected function prepareVideoOptions(): void
    {
        $this->videoOptions['autoplay'] ??= false;

        $lazy = ArrayHelper::remove($this->videoOptions, 'lazy', $this->videoOptions['autoplay'] && $this->defaultImageLoading === 'lazy');
        $lazyCssClass = ArrayHelper::remove($this->videoOptions, 'lazyCssClass');

        $this->videoOptions[$lazy ? 'data-src' : 'src'] ??= $this->asset->file->getUrl();
        $this->videoOptions['preload'] ??= $lazy ? 'none' : 'auto';

        $this->videoOptions['controls'] ??= !$this->videoOptions['autoplay'];
        $this->videoOptions['playsinline'] ??= true;

        $this->videoOptions['loop'] ??= !$this->videoOptions['controls'];

        if (!$this->videoOptions['controls']) {
            $this->videoOptions['muted'] ??= '';
        }

        if ($lazy && $lazyCssClass) {
            Html::addCssClass($this->videoOptions, $lazyCssClass);
        }
    }

    protected function addCssClass(): void
    {
        if ($classes = ($this->imgOptions['class'] ?? null)) {
            Html::addCssClass($this->videoOptions, $classes);
        }
    }
}
