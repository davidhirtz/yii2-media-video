<?php

declare(strict_types=1);

namespace Hirtz\Media\Video\Modules\Admin\Widgets\Grids\Columns;

use Hirtz\Media\Video\Modules\Admin\Widgets\StreamStatusBadge;
use Hirtz\Media\Video\Streaming\StreamManager;
use Hirtz\Skeleton\Html\Div;
use Hirtz\Skeleton\Html\Img;
use Hirtz\Skeleton\Html\Span;
use Hirtz\Skeleton\Html\Video;
use Hirtz\Skeleton\Widgets\Icon;
use Override;
use Stringable;
use Yii;

class Thumbnail extends \Hirtz\Media\Modules\Admin\Widgets\Grids\Columns\Thumbnail
{
    #[Override]
    protected function renderContent(): string|Stringable
    {
        if (!$this->file->isVideo()) {
            return parent::renderContent();
        }

        return Div::make()
            ->class('thumbnail thumbnail-video')
            ->content(
                $this->renderImage(),
                Span::make()
                    ->class('thumbnail-play')
                    ->content(Icon::make()->name('play')),
                $this->renderBadges(),
            );
    }

    #[Override]
    protected function renderImage(): string|Stringable
    {
        if (!$this->file->isVideo() || $this->file->getPreviewUrl() !== null) {
            return parent::renderImage();
        }

        $posterUrl = Yii::$app->has('videoStream') ? StreamManager::getComponent()->getPosterUrl($this->file) : null;

        // Without a preview the browser draws a frame itself, fetching little more than the video's header.
        return $posterUrl !== null
            ? Img::make()
                ->src($posterUrl)
                ->class('img-thumbnail')
                ->loading('lazy')
            : Video::make()
                ->class('img-thumbnail')
                ->attribute('src', $this->file->getUrl() . '#t=1')
                ->preload('metadata')
                ->muted()
                ->playsinline();
    }

    #[Override]
    protected function getBadges(): array
    {
        $badges = parent::getBadges();

        if (Yii::$app->has('videoStream') && StreamManager::getComponent()->getState($this->file) !== null) {
            $badges[] = StreamStatusBadge::make()
                ->file($this->file)
                ->label(false);
        }

        return $badges;
    }
}
