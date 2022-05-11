<?php

namespace davidhirtz\yii2\media\video\models\behaviors;

use davidhirtz\yii2\media\models\File;
use Exception;
use getID3;
use yii\base\Behavior;

/**
 * @property File $owner
 */
class FileVideoBehavior extends Behavior
{
    /**
     * @return string[]
     */
    public function events(): array
    {
        return [
            File::EVENT_BEFORE_VALIDATE => 'onBeforeValidate',
        ];
    }

    /**
     * @noinspection PhpUndefinedMethodInspection
     */
    public function onBeforeValidate()
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

    /**
     * @return bool
     */
    public function isVideo(): bool
    {
        return $this->owner->extension == 'mp4';
    }
}