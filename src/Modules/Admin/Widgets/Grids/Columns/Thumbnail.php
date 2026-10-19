<?php

declare(strict_types=1);

namespace Hirtz\Media\Video\Modules\Admin\Widgets\Grids\Columns;

use Hirtz\Skeleton\Html\Video;
use Override;
use Stringable;

class Thumbnail extends \Hirtz\Media\Modules\Admin\Widgets\Grids\Columns\Thumbnail
{
    #[Override]
    protected function renderContent(): string|Stringable
    {
        if (!$this->file->isVideo() || $this->file->getPreviewUrl()) {
            return parent::renderContent();
        }

        // Without a preview the browser draws a frame itself, fetching little more than the video's header.
        return Video::make()
            ->class('img-thumbnail')
            ->attribute('src', $this->file->getUrl() . '#t=1')
            ->preload('metadata')
            ->muted()
            ->playsinline();
    }
}
