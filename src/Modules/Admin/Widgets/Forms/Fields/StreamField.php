<?php

declare(strict_types=1);

namespace Hirtz\Media\Video\Modules\Admin\Widgets\Forms\Fields;

use Hirtz\Skeleton\Widgets\Forms\Fields\FormTextField;
use Hirtz\Skeleton\Widgets\Forms\FormText;
use Override;
use Stringable;

/**
 * Raw, since the content is markup whose texts were encoded when it was built.
 */
class StreamField extends FormTextField
{
    #[Override]
    protected function renderContent(): string|Stringable
    {
        return FormText::make()
            ->model($this->model)
            ->property($this->property)
            ->content($this->content)
            ->format('raw');
    }
}
