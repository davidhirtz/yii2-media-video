<?php

declare(strict_types=1);

namespace Hirtz\Media\Video\Streaming;

use Yii;

enum StreamStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Ready = 'ready';
    case Failed = 'failed';

    public function isInProgress(): bool
    {
        return $this === self::Pending || $this === self::Processing;
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => Yii::t('video', 'FILE_STREAM_STATUS_PENDING_LABEL'),
            self::Processing => Yii::t('video', 'FILE_STREAM_STATUS_PROCESSING_LABEL'),
            self::Ready => Yii::t('video', 'FILE_STREAM_STATUS_READY_LABEL'),
            self::Failed => Yii::t('video', 'FILE_STREAM_STATUS_FAILED_LABEL'),
        };
    }
}
