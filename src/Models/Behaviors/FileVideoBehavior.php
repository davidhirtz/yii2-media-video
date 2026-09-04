<?php

declare(strict_types=1);

namespace Hirtz\Media\Video\Models\Behaviors;

use Hirtz\Media\Models\File;
use Exception;
use getID3;
use Override;
use yii\base\Behavior;

/**
 * @extends Behavior<File>
 */
class FileVideoBehavior extends Behavior
{
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
                $getID3 = new getID3();
                $file = $getID3->analyze($this->owner->upload->tempName);
                $this->owner->width = $file['video']['resolution_x'] ?? null;
                $this->owner->height = $file['video']['resolution_y'] ?? null;
            } catch (Exception $exception) {
                $this->owner->addError('upload', $exception->getMessage());
            }
        }
    }
}
