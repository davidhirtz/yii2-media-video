<?php

declare(strict_types=1);

namespace Hirtz\Media\Video\Widgets;

use Closure;
use Hirtz\Skeleton\Html\Video;
use Override;
use Stringable;

class Media extends \Hirtz\Media\Widgets\Media
{
    protected bool $autoplay = false;
    /**
     * @var list<Closure>|null
     */
    private ?array $videoClosures = null;

    public function autoplay(bool $autoplay = true): static
    {
        $this->autoplay = $autoplay;
        return $this;
    }

    /**
     * @param Closure(Video): Video $video
     */
    public function video(Closure $video): static
    {
        $this->videoClosures[] = $video;
        return $this;
    }

    #[Override]
    protected function renderContent(): string|Stringable
    {
        return $this->asset->file->isVideo() ? $this->renderVideo() : $this->renderPicture();
    }

    protected function renderVideo(): string|Stringable
    {
        $video = Video::make()
            ->attribute($this->lazyLoading ? 'data-src' : 'src', $this->asset->file->getUrl())
            ->autoplay($this->autoplay)
            ->controls(!$this->autoplay)
            ->playsinline($this->autoplay)
            ->loop($this->autoplay)
            ->muted($this->autoplay)
            ->preload(!$this->autoplay ? ($this->lazyLoading ? 'none' : 'auto') : null);

        if ($this->aspectRatio) {
            $video->addStyle(['aspect-ratio' => $this->getAspectRatio()]);
        }

        return $this->evaluate($this->videoClosures, $video);
    }
}
