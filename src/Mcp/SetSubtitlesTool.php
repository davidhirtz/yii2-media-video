<?php

declare(strict_types=1);

namespace Hirtz\Media\Video\Mcp;

use Hirtz\Skeleton\Upload\Upload;
use Hirtz\Skeleton\Web\ContentUploadedFile;
use Hirtz\Skeleton\Mcp\Tools\ToolException;
use Override;
use Yii;

/**
 * The text is parked like an admin upload and assigned as its token, so the file's own validation and save store it.
 */
class SetSubtitlesTool extends SubtitlesTool
{
    #[Override]
    public function getName(): string
    {
        return 'set_subtitles';
    }

    #[Override]
    public function getTitle(): string
    {
        return 'Set the subtitles of a video';
    }

    #[Override]
    public function getDescription(): string
    {
        return 'Stores the WebVTT text as a video file\'s subtitles in one content language, replacing what it held; '
            . 'null removes them. The text must begin with "WEBVTT". Read the subtitles of another language with '
            . 'get_subtitles to translate them.';
    }

    #[Override]
    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                ...$this->getFileProperties(),
                'vtt' => ['type' => ['string', 'null'], 'description' => 'The WebVTT text, null to remove the subtitles.'],
            ],
            'required' => ['file', 'language', 'vtt'],
            'additionalProperties' => false,
        ];
    }

    #[Override]
    public function isReadOnly(): bool
    {
        return false;
    }

    #[Override]
    public function isIdempotent(): bool
    {
        return true;
    }

    #[Override]
    public function isDestructive(): bool
    {
        return true;
    }

    #[Override]
    protected function run(array $arguments): array
    {
        $file = $this->findVideo($arguments);
        $definition = $this->findDefinition($file);
        $attribute = $this->getAttributeName($file, $definition, $arguments);
        $vtt = $arguments['vtt'] ?? null;

        if (is_string($vtt) && $vtt !== '') {
            // Sniffing may name a VTT `text/plain`, so the name keeps the extension and the WEBVTT check decides
            $upload = Yii::$container->get(ContentUploadedFile::class, [], [
                'allowedExtensions' => $definition->getExtensions(),
                'content' => $vtt,
                'filename' => 'subtitles.vtt',
            ]);

            $token = $upload->error ? null : Upload::getComponent()->createTempFile($upload);

            if ($token === null) {
                throw new ToolException('The subtitles could not be stored.');
            }

            $file->setAttribute($attribute, $token);
        } else {
            $file->setAttribute($attribute, null);
        }

        if ($file->update() === false) {
            $this->throwValidationErrors($file);
        }

        $url = $definition->getUrl($file, $attribute);

        return [
            'file' => $this->getRecordSummary($file),
            'language' => $arguments['language'],
            'url' => $url !== null ? $this->getAbsoluteUrl($url) ?? $url : null,
        ];
    }
}
