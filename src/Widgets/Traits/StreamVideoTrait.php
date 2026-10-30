<?php

declare(strict_types=1);

namespace Hirtz\Media\Video\Widgets\Traits;

use Hirtz\Media\Models\File;
use Hirtz\Media\Video\Assets\HlsAssetBundle;
use Hirtz\Media\Video\Streaming\StreamManager;
use Hirtz\Skeleton\Html\Video;
use Hirtz\Skeleton\Web\View;
use Yii;

/**
 * @property View $view
 */
trait StreamVideoTrait
{
    protected function applyStream(Video $video, File $file, string $attribute = 'src'): Video
    {
        if (!Yii::$app->has('videoStream')) {
            return $video;
        }

        $manager = StreamManager::getComponent();
        $url = $manager->getPlaylistUrl($file);

        if ($url === null) {
            return $video;
        }

        HlsAssetBundle::register($this->view);

        return $video->attribute($attribute, $url)
            ->attribute('data-hls', true)
            ->attribute('poster', $manager->getPosterUrl($file));
    }
}
