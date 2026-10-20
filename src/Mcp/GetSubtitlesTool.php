<?php

declare(strict_types=1);

namespace Hirtz\Media\Video\Mcp;

use Hirtz\Skeleton\Upload\Upload;
use Override;

class GetSubtitlesTool extends SubtitlesTool
{
    #[Override]
    public function getName(): string
    {
        return 'get_subtitles';
    }

    #[Override]
    public function getTitle(): string
    {
        return 'Get the subtitles of a video';
    }

    #[Override]
    public function getDescription(): string
    {
        return 'Answers the WebVTT text of a video file\'s subtitles in one content language, null when it has none. '
            . 'find_records ("file", "missing": ["subtitles_de"]) finds the videos without them.';
    }

    #[Override]
    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => $this->getFileProperties(),
            'required' => ['file', 'language'],
            'additionalProperties' => false,
        ];
    }

    #[Override]
    public function isReadOnly(): bool
    {
        return true;
    }

    #[Override]
    protected function run(array $arguments): array
    {
        $file = $this->findVideo($arguments);
        $definition = $this->findDefinition($file);
        $attribute = $this->getAttributeName($file, $definition, $arguments);
        $url = $definition->getUrl($file, $attribute);

        $vtt = null;

        if ($url !== null) {
            $content = @file_get_contents(Upload::getComponent()->getFilePath($file, $attribute, (string)$file->getAttribute($attribute)));
            $vtt = $content !== false ? (str_starts_with($content, "\u{FEFF}") ? substr($content, 3) : $content) : null;
        }

        return [
            'file' => $this->getRecordSummary($file),
            'language' => $arguments['language'],
            'url' => $url !== null ? $this->getAbsoluteUrl($url) ?? $url : null,
            'vtt' => $vtt,
        ];
    }
}
