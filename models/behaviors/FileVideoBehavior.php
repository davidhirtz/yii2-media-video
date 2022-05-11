<?php

namespace davidhirtz\yii2\media\video\models\behaviors;

use davidhirtz\yii2\media\models\File;
use Exception;
use getID3;
use Yii;
use yii\base\Behavior;
use yii\validators\FileValidator;

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
            File::EVENT_CREATE_VALIDATORS => 'onCreateValidators',
            File::EVENT_BEFORE_VALIDATE => 'onBeforeValidate',
        ];
    }

    /**
     * Makes sure MP4 is added to the allowed extensions.
     */
    public function onCreateValidators()
    {
        if (!in_array('mp4', $this->owner->allowedExtensions)) {
            $this->owner->allowedExtensions[] = 'mp4';

            foreach ($this->owner->getValidators() as $validator) {
                if ($validator instanceof FileValidator) {
                    $validator->extensions = $this->owner->allowedExtensions;
                    break;
                }
            }
        }
    }

    /**
     * Sets `width` and `height` from video resolution.
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