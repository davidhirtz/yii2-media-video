<?php

declare(strict_types=1);

namespace Hirtz\Media\Video\Modules\Admin\Widgets\Forms\Fields;

use Hirtz\Skeleton\Html\Div;
use Hirtz\Skeleton\Widgets\Forms\Fields\Field;
use Hirtz\Skeleton\Widgets\Forms\FormRow;
use Override;
use Stringable;

/**
 * The items are the row's content, so its gap separates them; their texts were encoded when they were built.
 */
class StreamField extends Field
{
    /**
     * @var list<Stringable>
     */
    protected array $items = [];

    public function items(Stringable ...$items): static
    {
        $this->items = array_values($items);
        return $this;
    }

    #[Override]
    public function isDisabled(): bool
    {
        return true;
    }

    #[Override]
    public function isVisible(): bool
    {
        return $this->items !== [] && parent::isVisible();
    }

    #[Override]
    protected function renderContent(): string|Stringable
    {
        $this->rowAttributes['id'] ??= "{$this->getId()}-row";

        return FormRow::make()
            ->attributes($this->rowAttributes)
            ->header(Div::make()
                ->attributes($this->labelAttributes)
                ->addClass('label')
                ->text($this->label))
            ->content(...$this->items);
    }

    #[Override]
    protected function getInput(): string|Stringable
    {
        return '';
    }
}
