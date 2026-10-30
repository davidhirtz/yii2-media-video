<?php

declare(strict_types=1);

namespace Hirtz\Media\Video\Models\CustomAttributes;

use Hirtz\Media\Models\File;
use Hirtz\Media\Video\Streaming\StreamManager;
use Hirtz\Media\Video\Streaming\StreamState;
use Hirtz\Skeleton\Models\CustomAttributes\CustomAttribute;
use Hirtz\Skeleton\Widgets\Forms\Fields\Field;
use Hirtz\Skeleton\Widgets\Forms\Fields\FormTextField;
use Override;
use Stringable;
use Yii;
use yii\base\Model;

/**
 * Written by {@see StreamManager::saveState()} alone, never by a form.
 */
class StreamCustomAttribute extends CustomAttribute
{
    public function __construct(string $name)
    {
        parent::__construct($name);

        $this->label = Yii::t('video', 'FILE_STREAM_LABEL');
        $this->plainText = true;
        $this->visible = static fn (File $file): bool => $file->isVideo();
    }

    #[Override]
    protected function getValidationRules(Model $owner): array
    {
        return [];
    }

    #[Override]
    public function createField(Model $owner): Field
    {
        $value = $this->formatValue($owner, $owner->{$this->name});
        return $this->configureField(FormTextField::make()->content(is_array($value) ? null : $value), $owner);
    }

    #[Override]
    public function serialize(mixed $value): mixed
    {
        return $value instanceof StreamState ? $value->toArray() : null;
    }

    #[Override]
    public function unserialize(mixed $value): mixed
    {
        return StreamState::fromArray($value);
    }

    /**
     * Takes the array a trail stored, too.
     */
    #[Override]
    public function formatValue(Model $owner, mixed $value): string|Stringable|array|null
    {
        $state = $value instanceof StreamState ? $value : StreamState::fromArray($value);

        if ($state === null) {
            return null;
        }

        if (Yii::$app->has('videoStream') && StreamManager::getComponent()->isForeignState($state)) {
            return Yii::t('video', 'FILE_STREAM_STATUS_FOREIGN_LABEL');
        }

        $status = $state->status->getLabel();

        return $state->error !== null
            ? Yii::t('video', 'FILE_STREAM_ERROR_FORMAT', ['status' => $status, 'error' => $state->error])
            : $status;
    }

    #[Override]
    public function afterDelete(Model $owner, string $name): void
    {
        if ($owner instanceof File && Yii::$app->has('videoStream')) {
            StreamManager::getComponent()->deleteRemote($owner);
        }
    }
}
