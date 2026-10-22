<?php

declare(strict_types=1);

namespace Hirtz\Media\Video\Console\Controllers;

use Hirtz\Media\Models\File;
use Hirtz\Media\Video\Helpers\VideoAnalyzer;

/**
 * Handles media module files and reads the duration of videos.
 */
class FileController extends \Hirtz\Media\Console\Controllers\FileController
{
    /**
     * Reads the duration of every video stored without one.
     *
     * @noinspection PhpUnused
     */
    public function actionDuration(): void
    {
        $count = 0;

        /** @var File $file */
        foreach (File::find()->andWhere(['extension' => File::getModule()->videoExtensions])->each() as $file) {
            if (!$file->isVideo() || !$file->hasAttribute('duration') || $file->getAttribute('duration') !== null) {
                continue;
            }

            $duration = VideoAnalyzer::getDuration(VideoAnalyzer::analyze($file->getFilePath()));

            if ($duration !== null) {
                $file->setAttribute('duration', $duration);
                $file->updateAttributes([$file->getCustomAttributesColumn() => $file->getSerializedCustomAttributesColumn()]);
                $count++;
            }
        }

        $this->stdout("The duration of $count videos was read" . PHP_EOL);
    }
}
