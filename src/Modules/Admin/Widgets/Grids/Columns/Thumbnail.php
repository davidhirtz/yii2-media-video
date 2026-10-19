<?php

declare(strict_types=1);

namespace Hirtz\Media\Video\Modules\Admin\Widgets\Grids\Columns;

use Hirtz\Skeleton\Html\Div;
use Hirtz\Skeleton\Html\Span;
use Hirtz\Skeleton\Html\Video;
use Hirtz\Skeleton\Widgets\Icon;
use Override;
use Stringable;

class Thumbnail extends \Hirtz\Media\Modules\Admin\Widgets\Grids\Columns\Thumbnail
{
    #[Override]
    protected function renderContent(): string|Stringable
    {
        if (!$this->file->isVideo()) {
            return parent::renderContent();
        }

        // Without a preview the browser draws a frame itself, fetching little more than the video's header.
        $thumbnail = $this->file->getPreviewUrl()
            ? parent::renderContent()
            : Video::make()
                ->class('img-thumbnail')
                ->attribute('src', $this->file->getUrl() . '#t=1')
                ->preload('metadata')
                ->muted()
                ->playsinline();

        return Div::make()
            ->class('thumbnail thumbnail-video')
            ->content($thumbnail, Span::make()
                ->class('thumbnail-play')
                ->content(Icon::make()->name('play')));
    }
}
