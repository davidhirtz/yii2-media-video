<?php

declare(strict_types=1);

namespace Hirtz\Media\Video\Tests;

use Hirtz\Media\Models\File;
use Hirtz\Media\Video\Bootstrap;
use Hirtz\Media\Test\TestCase;

class VideoExtensionsTest extends TestCase
{
    public function testTheBootstrapsExtensionsAreTheOnesReadAsVideo(): void
    {
        $bootstrap = new Bootstrap();
        $module = File::getModule();

        self::assertSame($bootstrap->allowedVideoExtensions, $module->videoExtensions);

        foreach ($bootstrap->allowedVideoExtensions as $extension) {
            self::assertContains($extension, $module->allowedExtensions);
        }
    }

    public function testAnAddedExtensionIsReadAsVideo(): void
    {
        File::getModule()->videoExtensions = ['mp4', 'mov'];

        $file = File::create();
        $file->extension = 'mov';

        self::assertTrue($file->isVideo());

        $file->extension = 'webm';
        self::assertFalse($file->isVideo());
    }

    public function testAProjectsOwnVideoExtensionsSurvive(): void
    {
        $this->config['modules']['media']['videoExtensions'] = ['mp4', 'mov'];
        $this->reloadApplication();

        self::assertSame(['mp4', 'mov'], File::getModule()->videoExtensions);
    }
}
