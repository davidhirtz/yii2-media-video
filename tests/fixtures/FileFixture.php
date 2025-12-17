<?php

declare(strict_types=1);

namespace Hirtz\Media\Video\tests\fixtures;

use Hirtz\Media\Models\File;
use yii\test\ActiveFixture;

class FileFixture extends ActiveFixture
{
    public $modelClass = File::class;
}
