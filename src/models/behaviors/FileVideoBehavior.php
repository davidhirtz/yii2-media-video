<?php

namespace davidhirtz\yii2\media\video\models\behaviors;

use davidhirtz\yii2\media\models\File;
use Exception;
use getID3;
use yii\base\Behavior;
use yii\validators\FileValidator;

/**
 * @property File $owner
 */
class FileVideoBehavior extends Behavior
{
    public array $allowedVideoExtensions = ['mp4', 'webm'];

    public function events(): array
    {
        return [
            File::EVENT_CREATE_VALIDATORS => $this->onCreateValidators(...),
            File::EVENT_BEFORE_VALIDATE => $this->onBeforeValidate(...),
        ];
    }

    public function onCreateValidators(): void
    {
        $updateFileValidators = false;

        foreach ($this->allowedVideoExtensions as $extension) {
            if (!in_array($extension, $this->owner->allowedExtensions)) {
                $this->owner->allowedExtensions[] = $extension;
                $updateFileValidators = true;
            }
        }

        if ($updateFileValidators) {
            foreach ($this->owner->getValidators() as $validator) {
                if ($validator instanceof FileValidator) {
                    $validator->extensions = $this->owner->allowedExtensions;
                    break;
                }
            }
        }
    }

    public function onBeforeValidate(): void
    {
        /** @noinspection PhpUndefinedMethodInspection {@uses self::isVideo()} */
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

    public function isVideo(): bool
    {
        return in_array($this->owner->extension, $this->allowedVideoExtensions);
    }
}