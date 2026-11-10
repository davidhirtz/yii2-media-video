<?php

declare(strict_types=1);

namespace Hirtz\Media\Video\Widgets\StructuredData;

use DateTimeInterface;
use Hirtz\Media\Models\File;
use Hirtz\Media\Video\Streaming\StreamManager;
use Hirtz\Skeleton\Helpers\StructuredData;
use Hirtz\Skeleton\Helpers\Url;
use Hirtz\Skeleton\Widgets\StructuredData\Thing;
use Override;
use Yii;

class VideoObject extends Thing
{
    protected ?string $type = 'VideoObject';
    protected ?File $file = null;
    protected ?string $name = null;
    protected ?string $description = null;
    protected ?string $thumbnailUrl = null;
    protected ?DateTimeInterface $uploadDate = null;

    public function file(?File $file): static
    {
        $this->file = $file;
        return $this;
    }

    public function name(?string $name): static
    {
        $this->name = $name;
        return $this;
    }

    public function description(?string $description): static
    {
        $this->description = $description;
        return $this;
    }

    /**
     * Google requires a thumbnail: without a stream's poster, the project names one.
     */
    public function thumbnailUrl(?string $thumbnailUrl): static
    {
        $this->thumbnailUrl = $thumbnailUrl;
        return $this;
    }

    public function uploadDate(?DateTimeInterface $uploadDate): static
    {
        $this->uploadDate = $uploadDate;
        return $this;
    }

    #[Override]
    protected function configure(): void
    {
        if ($this->file !== null) {
            $this->name ??= $this->file->name;
            $this->description ??= $this->file->alt_text;
            $this->thumbnailUrl ??= $this->getPosterUrl($this->file);
            $this->uploadDate ??= $this->file->created_at;
        }

        parent::configure();
    }

    #[Override]
    public function isVisible(): bool
    {
        return $this->file?->isVideo()
            && $this->name
            && $this->thumbnailUrl
            && $this->uploadDate !== null
            && parent::isVisible();
    }

    #[Override]
    protected function getProperties(): array
    {
        $duration = $this->file?->hasAttribute('duration') ? $this->file->getAttribute('duration') : null;

        return [
            'name' => $this->name,
            'description' => $this->description,
            'thumbnailUrl' => $this->thumbnailUrl ? Url::to($this->thumbnailUrl, true) : null,
            'uploadDate' => StructuredData::date($this->uploadDate),
            'duration' => is_int($duration) ? $this->formatDuration($duration) : null,
            'contentUrl' => $this->file !== null ? Url::to($this->file->getUrl(), true) : null,
            'width' => $this->file?->width,
            'height' => $this->file?->height,
            ...parent::getProperties(),
        ];
    }

    protected function getPosterUrl(File $file): ?string
    {
        return Yii::$app->has('videoStream') ? StreamManager::getComponent()->getPosterUrl($file) : null;
    }

    protected function formatDuration(int $seconds): string
    {
        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        $seconds %= 60;

        return 'PT' . ($hours ? "{$hours}H" : '') . ($minutes ? "{$minutes}M" : '') . ($seconds || !($hours || $minutes) ? "{$seconds}S" : '');
    }
}
