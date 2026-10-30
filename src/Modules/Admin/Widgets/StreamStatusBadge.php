<?php

declare(strict_types=1);

namespace Hirtz\Media\Video\Modules\Admin\Widgets;

use Hirtz\Media\Traits\FilePropertyTrait;
use Hirtz\Media\Video\Streaming\StreamManager;
use Hirtz\Media\Video\Streaming\StreamStatus;
use Hirtz\Skeleton\Html\Span;
use Hirtz\Skeleton\Widgets\Icon;
use Hirtz\Skeleton\Widgets\Widget;
use Override;
use Stringable;
use Yii;

class StreamStatusBadge extends Widget
{
    use FilePropertyTrait;

    protected bool $label = true;

    /**
     * Without the label, the status is the icon's tooltip.
     */
    public function label(bool $label = true): static
    {
        $this->label = $label;
        return $this;
    }

    #[Override]
    protected function renderContent(): string|Stringable
    {
        if (!Yii::$app->has('videoStream')) {
            return '';
        }

        $manager = StreamManager::getComponent();
        $state = $manager->getState($this->file);

        if ($state === null) {
            return '';
        }

        [$class, $icon] = $manager->isForeignState($state) ? ['badge-warning', 'right-left'] : match ($state->status) {
            StreamStatus::Ready => ['badge-success', 'circle-check'],
            StreamStatus::Processing => ['badge-info', 'hourglass-half'],
            StreamStatus::Pending => ['', 'clock'],
            StreamStatus::Failed => ['badge-error', 'triangle-exclamation'],
        };

        $value = $this->file->getCustomAttribute(StreamManager::ATTRIBUTE)?->formatValue($this->file, $state);
        $value = is_array($value) ? '' : (string)$value;
        $icon = Icon::make()->name($icon);

        return Span::make()
            ->class(trim("badge $class"))
            ->content(...$this->label
                ? [$icon, Span::make()->text($value)]
                : [$icon->tooltip($this->file->getAttributeLabel(StreamManager::ATTRIBUTE) . ": $value")]);
    }
}
