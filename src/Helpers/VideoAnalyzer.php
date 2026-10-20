<?php

declare(strict_types=1);

namespace Hirtz\Media\Video\Helpers;

use getID3;
use Hirtz\Skeleton\Helpers\FileHelper;

class VideoAnalyzer
{
    /**
     * getID3 reads a local path, so a file behind a stream wrapper is copied to a temporary file first.
     *
     * @return array<string, mixed>
     */
    public static function analyze(string $path): array
    {
        if (stream_is_local($path)) {
            return (new getID3())->analyze($path);
        }

        $temp = tempnam(sys_get_temp_dir(), 'video');

        if ($temp === false || !@copy($path, $temp)) {
            return [];
        }

        try {
            return (new getID3())->analyze($temp);
        } finally {
            FileHelper::unlink($temp);
        }
    }

    /**
     * @param array<string, mixed> $info
     * @return int|null the duration in whole seconds
     */
    public static function getDuration(array $info): ?int
    {
        $seconds = $info['playtime_seconds'] ?? null;
        return is_int($seconds) || is_float($seconds) ? (int)round($seconds) : null;
    }
}
