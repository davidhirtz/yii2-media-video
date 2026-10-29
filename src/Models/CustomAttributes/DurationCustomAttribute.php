<?php

declare(strict_types=1);

namespace Hirtz\Media\Video\Models\CustomAttributes;

use Hirtz\Media\Models\File;
use Hirtz\Skeleton\Models\CustomAttributes\NumberCustomAttribute;
use Override;
use Stringable;
use Yii;
use yii\base\Model;

/**
 * Read from the upload by {@see \Hirtz\Media\Video\Models\Behaviors\FileVideoBehavior}.
 */
class DurationCustomAttribute extends NumberCustomAttribute
{
    public function __construct(string $name)
    {
        parent::__construct($name);

        $this->label = Yii::t('video', 'FILE_DURATION_LABEL');
        $this->min = 0;
        $this->plainText = true;
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
}
