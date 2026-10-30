<?php

declare(strict_types=1);

namespace Hirtz\Media\Video\Models\Behaviors;

use Hirtz\Media\Models\File;
use Hirtz\Media\Video\Streaming\StreamManager;
use Override;
use yii\base\Behavior;

/**
 * Keeps its own upload flag: `File::afterSave()` clears `$upload` before the after-save events.
 *
 * @extends Behavior<File>
 */
class FileStreamBehavior extends Behavior
{
    private bool $isUploaded = false;

    /**
     * @return array<string, callable>
     */
    #[Override]
    public function events(): array
    {
        return [
            File::EVENT_BEFORE_INSERT => $this->onBeforeSave(...),
            File::EVENT_BEFORE_UPDATE => $this->onBeforeSave(...),
            File::EVENT_AFTER_INSERT => $this->onAfterSave(...),
            File::EVENT_AFTER_UPDATE => $this->onAfterSave(...),
        ];
    }

    public function onBeforeSave(): void
    {
        $this->isUploaded = $this->owner->upload !== null;
    }

    /**
     * A duplicate is an upload as well, so it gets its own remote video rather than sharing the source's.
     */
    public function onAfterSave(): void
    {
        if (!$this->isUploaded) {
            return;
        }

        $this->isUploaded = false;
        $manager = StreamManager::getComponent();

        if ($this->owner->isVideo()) {
            $manager->schedule($this->owner);
        } elseif ($manager->getState($this->owner)) {
            $manager->deleteRemote($this->owner);
            $manager->saveState($this->owner, null);
        }
    }
}
