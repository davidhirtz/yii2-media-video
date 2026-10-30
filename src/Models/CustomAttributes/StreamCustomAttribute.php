<?php

declare(strict_types=1);

namespace Hirtz\Media\Video\Models\CustomAttributes;

use Hirtz\Media\Models\File;
use Hirtz\Media\Video\Modules\Admin\Widgets\Forms\Fields\StreamField;
use Hirtz\Media\Video\Modules\Admin\Widgets\StreamStatusBadge;
use Hirtz\Media\Video\Streaming\StreamManager;
use Hirtz\Media\Video\Streaming\StreamState;
use Hirtz\Skeleton\Html\A;
use Hirtz\Skeleton\Html\Custom\RelativeTime;
use Hirtz\Skeleton\Html\Div;
use Hirtz\Skeleton\Html\Span;
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
        if ($owner instanceof File) {
            return $this->configureField(StreamField::make()->items(...$this->renderDetails($owner)), $owner);
        }

        $value = $this->formatValue($owner, $owner->{$this->name});
        return $this->configureField(FormTextField::make()->content(is_array($value) ? null : $value), $owner);
    }

    /**
     * Always plain text, and richer than the formatted value the parent renders for one.
     */
    #[Override]
    public function getField(Model $owner): Field
    {
        return $this->createField($owner);
    }

    /**
     * Another installation's video is neither named nor linked: it lives in a scope this one has no account for.
     *
     * @return list<Stringable>
     */
    protected function renderDetails(File $file): array
    {
        if (!Yii::$app->has('videoStream')) {
            return [];
        }

        $manager = StreamManager::getComponent();
        $state = $manager->getState($file);

        if ($state === null) {
            return [];
        }

        $provider = $manager->getProvider();
        $details = [];

        if ($state->id !== null && !$manager->isForeignState($state)) {
            $url = $provider->getDashboardUrl($state);

            $details[] = Div::make()->content(
                Span::make()->text($provider->getLabel() . ' · '),
                $url !== null
                    ? A::make()
                        ->href($url)
                        ->target('_blank')
                        ->attribute('rel', 'noopener')
                        ->attribute('title', Yii::t('video', 'FILE_STREAM_DASHBOARD_TOOLTIP', ['provider' => $provider->getLabel()]))
                        ->text($state->id)
                    : Span::make()->text($state->id),
            );
        }

        $details[] = Div::make()->content(Yii::t('video', 'FILE_STREAM_SUBMITTED_TEXT', [
            'timestamp' => RelativeTime::make()->value($state->submittedAt),
        ]));

        if ($state->readyAt !== null) {
            $details[] = Div::make()->content(Yii::t('video', 'FILE_STREAM_READY_TEXT', [
                'timestamp' => RelativeTime::make()->value($state->readyAt),
            ]));
        }

        return [
            Div::make()->content(StreamStatusBadge::make()->file($file)),
            Div::make()
                ->class('small text-muted')
                ->content(...$details),
        ];
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
