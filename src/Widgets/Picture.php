<?php

declare(strict_types=1);

namespace Hirtz\Media\Video\Widgets;

use Hirtz\Skeleton\Helpers\Html;
use Hirtz\Skeleton\Html\Video;
use Override;
use Stringable;
use yii\helpers\ArrayHelper;

class Picture extends \Hirtz\Media\widgets\Picture
{
    protected array $videoAttributes = [];

    public function videoAttributes(array $attributes): static
    {
        $this->videoAttributes = $attributes;
        return $this;
    }

    #[Override]
    public function configure(): void
    {
        $this->prepareVideoOptions();
        $this->addCssClass();

        parent::configure();
    }

    #[Override]
    protected function renderContent(): string|Stringable
    {
        return $this->asset->file->isVideo() ? $this->getVideoTag() : $this->getPictureTag();
    }

    public function getVideoTag(): string|Stringable
    {
        return Video::make()
            ->attributes($this->videoAttributes);
    }

    protected function prepareVideoOptions(): void
    {
        $this->videoAttributes['autoplay'] ??= false;

        $lazy = ArrayHelper::remove($this->videoAttributes, 'lazy', $this->videoAttributes['autoplay'] && $this->defaultImageLoading === 'lazy');
        $lazyCssClass = ArrayHelper::remove($this->videoAttributes, 'lazyCssClass');

        $this->videoAttributes[$lazy ? 'data-src' : 'src'] ??= $this->asset->file->getUrl();
        $this->videoAttributes['preload'] ??= $lazy ? 'none' : 'auto';

        $this->videoAttributes['controls'] ??= !$this->videoAttributes['autoplay'];
        $this->videoAttributes['playsinline'] ??= true;

        $this->videoAttributes['loop'] ??= !$this->videoAttributes['controls'];

        if (!$this->videoAttributes['controls']) {
            $this->videoAttributes['muted'] ??= '';
        }

        if ($lazy && $lazyCssClass) {
            Html::addCssClass($this->videoAttributes, $lazyCssClass);
        }
    }

    protected function addCssClass(): void
    {
        if ($classes = ($this->imgAttributes['class'] ?? null)) {
            Html::addCssClass($this->videoAttributes, $classes);
        }
    }
}
