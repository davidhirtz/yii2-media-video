<?php

declare(strict_types=1);

namespace Hirtz\Media\Video\Tests;

use Hirtz\Media\Test\TestCase;
use Hirtz\Media\Video\Assets\VideoAssetBundle;
use Hirtz\Skeleton\Web\Application;
use Yii;

/**
 * The bundle hardcoded a moved `@vendor` path that no test rendered (monorepo issue #239).
 */
class VideoAssetBundleTest extends TestCase
{
    public function testTheSourcePathHoldsThePlaceholder(): void
    {
        $bundle = new VideoAssetBundle();

        self::assertFileExists(Yii::getAlias($bundle->sourcePath) . '/video.svg');
    }

    public function testTheBundlePublishes(): void
    {
        $bundle = VideoAssetBundle::register(Application::current()->getView());

        self::assertIsString($bundle->baseUrl);
        self::assertFileExists("$bundle->basePath/video.svg");
    }
}
