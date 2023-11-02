<?php

namespace davidhirtz\yii2\media\video\components\helpers;

use davidhirtz\yii2\skeleton\helpers\Html;
use yii\helpers\ArrayHelper;

class Video
{
    public static function tag(string $src, array $options = []): string
    {
        $autoplay = ArrayHelper::remove($options, 'autoplay', false);
        $lazy = ArrayHelper::remove($options, 'lazy', false);

        $options[$lazy ? 'data-src' : 'src'] ??= $src;
        $options['preload'] ??= $lazy ? 'none' : 'auto';

        $options['controls'] ??= !$autoplay;
        $options['playsinline'] ??= true;

        $options['autoplay'] ??= !$options['controls'];
        $options['loop'] ??= !$options['controls'];
        $options['muted'] ??= !$options['controls'] ? '' : null;

        return Html::tag('video', '', $options);
    }
}