<?php

declare(strict_types=1);

namespace Hirtz\Media\Video\Widgets;

use Closure;
use Hirtz\Media\Video\Models\CustomAttributes\SubtitlesCustomAttribute;
use Hirtz\Media\Video\Widgets\Traits\StreamVideoTrait;
use Hirtz\Skeleton\Html\Video;
use Override;
use Stringable;

class Media extends \Hirtz\Media\Widgets\Media
{
    use StreamVideoTrait;

    protected bool $autoplay = false;
    protected bool $subtitles = true;
    /**
     * @var list<Closure>|null
     */
    private ?array $videoClosures = null;

    public function autoplay(bool $autoplay = true): static
    {
        $this->autoplay = $autoplay;
        return $this;
    }

    public function subtitles(bool $subtitles = true): static
    {
        $this->subtitles = $subtitles;
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
        $attribute = $this->lazyLoading ? 'data-src' : 'src';

        $video = Video::make()
            ->attribute($attribute, $this->asset->file->getUrl())
            ->autoplay($this->autoplay)
            ->controls(!$this->autoplay)
            ->playsinline($this->autoplay)
            ->loop($this->autoplay)
            ->muted($this->autoplay)
            ->preload(!$this->autoplay ? ($this->lazyLoading ? 'none' : 'auto') : null);

        $this->applyStream($video, $this->asset->file, $attribute);

        if ($this->subtitles) {
            $video->content(...SubtitlesCustomAttribute::getFileTracks($this->asset->file));
        }

        if ($this->aspectRatio) {
            $video->addStyle(['aspect-ratio' => $this->getAspectRatio()]);
        }

        return $this->evaluate($this->videoClosures, $video);
    }
}
