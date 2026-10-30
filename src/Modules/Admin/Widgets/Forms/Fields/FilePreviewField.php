<?php

declare(strict_types=1);

namespace Hirtz\Media\Video\Modules\Admin\Widgets\Forms\Fields;

use Hirtz\Media\Video\Models\CustomAttributes\SubtitlesCustomAttribute;
use Hirtz\Media\Video\Widgets\Traits\StreamVideoTrait;
use Hirtz\Skeleton\Html\Video;
use Override;
use Stringable;

class FilePreviewField extends \Hirtz\Media\Modules\Admin\Widgets\Forms\Fields\FilePreviewField
{
    use StreamVideoTrait;

    #[Override]
    protected function hasPreview(): bool
    {
        return $this->file->isVideo() || parent::hasPreview();
    }

    #[Override]
    protected function getContent(): string|Stringable
    {
        return $this->file->isVideo() ? $this->renderVideoTag() : parent::getContent();
    }

    protected function renderVideoTag(): string|Stringable
    {
        $video = Video::make()
            ->attribute('src', $this->file->getUrl())
            ->class('img-transparent')
            ->addStyle(['width' => '100%'])
            ->controls()
            ->content(...SubtitlesCustomAttribute::getFileTracks($this->file));

        return $this->applyStream($video, $this->file);
    }
}
