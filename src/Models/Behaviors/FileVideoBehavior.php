<?php

declare(strict_types=1);

namespace Hirtz\Media\Video\Models\Behaviors;

use Hirtz\Media\Models\File;
use Exception;
use Hirtz\Media\Video\Helpers\VideoAnalyzer;
use Override;
use yii\base\Behavior;

/**
 * @extends Behavior<File>
 */
class FileVideoBehavior extends Behavior
{
    /**
     * @return array<string, callable>
     */
    #[Override]
    public function events(): array
    {
        return [
            File::EVENT_BEFORE_VALIDATE => $this->onBeforeValidate(...),
        ];
    }

    public function onBeforeValidate(): void
    {
        if ($this->owner->upload && $this->owner->isVideo()) {
            try {
                $info = VideoAnalyzer::analyze($this->owner->upload->tempName);
                $this->owner->width = $info['video']['resolution_x'] ?? null;
                $this->owner->height = $info['video']['resolution_y'] ?? null;

                if ($this->owner->hasAttribute('duration')) {
                    $this->owner->setAttribute('duration', VideoAnalyzer::getDuration($info));
                }
            } catch (Exception $exception) {
                $this->owner->addError('upload', $exception->getMessage());
            }
        }
    }
}
