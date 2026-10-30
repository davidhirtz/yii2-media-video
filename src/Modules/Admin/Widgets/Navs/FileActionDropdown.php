<?php

declare(strict_types=1);

namespace Hirtz\Media\Video\Modules\Admin\Widgets\Navs;

use Hirtz\Media\Video\Modules\Admin\Controllers\StreamController;
use Hirtz\Media\Video\Streaming\StreamManager;
use Hirtz\Skeleton\Widgets\Buttons\ConfirmButton;
use Override;
use Stringable;
use Yii;

class FileActionDropdown extends \Hirtz\Media\Modules\Admin\Widgets\Navs\FileActionDropdown
{
    #[Override]
    protected function configure(): void
    {
        if (Yii::$app->has('videoStream') && StreamManager::getComponent()->canResubmit($this->model)) {
            $this->addItem($this->getStreamResubmitButton());
        }

        parent::configure();
    }

    /**
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
