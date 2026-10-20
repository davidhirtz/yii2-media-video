<?php

declare(strict_types=1);

namespace Hirtz\Media\Video\Models\CustomAttributes;

use Hirtz\Media\Models\File;
use Hirtz\Media\Video\Modules\Admin\Widgets\Forms\Fields\FormTextField;
use Hirtz\Skeleton\Models\CustomAttributes\NumberCustomAttribute;
use Hirtz\Skeleton\Widgets\Forms\Fields\Field;
use Override;
use Stringable;
use Yii;
use yii\base\Model;

/**
 * Whole seconds, read from the upload by {@see \Hirtz\Media\Video\Models\Behaviors\FileVideoBehavior}.
 */
class DurationCustomAttribute extends NumberCustomAttribute
{
    public function __construct(string $name)
    {
        parent::__construct($name);

        $this->label = Yii::t('video', 'FILE_DURATION_LABEL');
        $this->min = 0;
        $this->disabled = true;
        $this->visible = static fn (File $file): bool => $file->isVideo();
    }

    #[Override]
    public function formatValue(Model $owner, mixed $value): string|Stringable|array|null
    {
        if (!is_int($value)) {
            return null;
        }

        return sprintf('%d:%02d:%02d', intdiv($value, 3600), intdiv($value % 3600, 60), $value % 60);
    }

    #[Override]
    public function createField(Model $owner): Field
    {
        $value = $this->formatValue($owner, $owner->{$this->name});
        return $this->configureField(FormTextField::make()->content(is_string($value) ? $value : null), $owner);
    }
}
