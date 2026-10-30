<?php

declare(strict_types=1);

namespace Hirtz\Media\Video\Assets;

use yii\web\AssetBundle;
use yii\web\View;

class HlsAssetBundle extends AssetBundle
{
    public $js = ['js/hls.js'];

    /**
     * In the head, so the `head-support` extension carries it across an admin swap; hls.js itself is a chunk loaded
     * only by a browser without native HLS.
     */
    public $jsOptions = [
        'position' => View::POS_HEAD,
        'type' => 'module',
    ];

    public $sourcePath = '@media-video/../resources/assets/dist';
}
