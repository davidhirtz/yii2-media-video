<?php

declare(strict_types=1);

namespace Hirtz\Media\Video\Modules\Admin\Widgets\Grids\Columns;

use Hirtz\Media\Video\Assets\VideoAssetBundle;
use Hirtz\Skeleton\Html\Div;
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

        $bundle = VideoAssetBundle::register($this->view);

        return Div::make()
            ->class('img-thumbnail')
            ->addStyle([
                'background' => "#000 url($bundle->baseUrl/video.svg) center no-repeat",
                'background-size' => '50%',
            ]);
    }
}
