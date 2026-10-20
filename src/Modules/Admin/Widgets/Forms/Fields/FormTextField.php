<?php

declare(strict_types=1);

namespace Hirtz\Media\Video\Modules\Admin\Widgets\Forms\Fields;

use Hirtz\Skeleton\Widgets\Forms\Fields\Field;
use Hirtz\Skeleton\Widgets\Forms\FormText;
use Override;
use Stringable;

/**
 * A custom attribute's value shown as text, like the file's size, where a definition must answer a field.
 */
class FormTextField extends Field
{
    protected string|Stringable|null $content = null;

    public function content(string|Stringable|null $content): static
    {
        $this->content = $content;
        return $this;
    }

    #[Override]
    public function isVisible(): bool
    {
        return $this->content !== null && $this->content !== '' && parent::isVisible();
    }

    #[Override]
    protected function renderContent(): string|Stringable
    {
        return FormText::make()
            ->model($this->model)
            ->property($this->property)
            ->content($this->content);
    }

    #[Override]
    protected function getInput(): string|Stringable
    {
        return '';
    }
}
