<?php

declare(strict_types=1);

namespace Hirtz\Media\Video\Assets;

use yii\web\AssetBundle;
use yii\web\View;

class VideoPreviewAssetBundle extends AssetBundle
{
    public $js = ['js/preview.js'];

    /**
     * In the head, so the `head-support` extension carries it across the swap of a grid it was registered by.
     */
    public $jsOptions = [
        'position' => View::POS_HEAD,
        'type' => 'module',
    ];

    public $sourcePath = '@media-video/../resources/assets/dist';
}
