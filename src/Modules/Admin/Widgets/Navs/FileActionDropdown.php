<?php

declare(strict_types=1);

namespace Hirtz\Media\Video\Modules\Admin\Widgets\Navs;

use Hirtz\Media\Video\Modules\Admin\Controllers\StreamController;
use Hirtz\Skeleton\Widgets\Buttons\ConfirmButton;
use Override;
use Stringable;
use Yii;

class FileActionDropdown extends \Hirtz\Media\Modules\Admin\Widgets\Navs\FileActionDropdown
{
    #[Override]
    protected function configure(): void
    {
        if ($this->model->isVideo() && Yii::$app->has('videoStream')) {
            $this->addItem($this->getStreamResubmitButton());
        }

        parent::configure();
    }

    /**
     * Offered in every state, which also moves a video to another provider.
     *
     * @see StreamController::actionResubmit()
     */
    protected function getStreamResubmitButton(): ?Stringable
    {
        return ConfirmButton::make()
            ->icon('rotate')
            ->label(Yii::t('video', 'FILE_STREAM_RESUBMIT_BUTTON'))
            ->title(Yii::t('video', 'FILE_STREAM_RESUBMIT_CONFIRM'))
            ->url(['/admin/media/stream/resubmit', 'id' => $this->model->id]);
    }
}
