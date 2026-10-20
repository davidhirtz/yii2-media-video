<?php

declare(strict_types=1);

namespace Hirtz\Media\Video\Mcp;

use Hirtz\Media\Models\File;
use Hirtz\Media\Video\Models\CustomAttributes\SubtitlesCustomAttribute;
use Hirtz\Skeleton\Mcp\Tools\ResourceTool;
use Hirtz\Skeleton\Mcp\Tools\ToolException;
use Hirtz\Skeleton\Web\User as WebUser;
use Override;
use Yii;

abstract class SubtitlesTool extends ResourceTool
{
    /**
     * Only beside the `file` resource, through which the agent finds the videos.
     */
    #[Override]
    public function isAvailable(): bool
    {
        return $this->mcp->getResource('file') !== null && (WebUser::current()?->can(File::AUTH_FILE) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    protected function getFileProperties(): array
    {
        return [
            'file' => ['type' => 'integer', 'description' => 'The id of the video file.'],
            'language' => [
                'enum' => Yii::$app->getI18n()->getLanguages(),
                'description' => 'The content language of the subtitles.',
            ],
        ];
    }

    /**
     * @param array<string, mixed> $arguments
     */
    protected function findVideo(array $arguments): File
    {
        $resource = $this->isAvailable() ? $this->mcp->getResource('file') : null;

        if ($resource === null) {
            throw new ToolException('Resource not found: file');
        }

        $file = $this->findRecord($resource, $arguments['file'] ?? null);

        if (!$file instanceof File || !$file->isVideo()) {
            throw new ToolException('File ' . (is_scalar($arguments['file']) ? $arguments['file'] : '') . ' is not a video.');
        }

        return $file;
    }

    protected function findDefinition(File $file): SubtitlesCustomAttribute
    {
        foreach ($file->getCustomAttributeDefinitions() as $definition) {
            if ($definition instanceof SubtitlesCustomAttribute) {
                return $definition;
            }
        }

        throw new ToolException('Files take no subtitles here.');
    }

    /**
     * @param array<string, mixed> $arguments
     */
    protected function getAttributeName(File $file, SubtitlesCustomAttribute $definition, array $arguments): string
    {
        $language = $arguments['language'] ?? null;
        $i18n = Yii::$app->getI18n();

        if (!is_string($language) || !$i18n->hasLanguage($language)) {
            throw new ToolException('Unknown language, one of: ' . implode(', ', $i18n->getLanguages()) . '.');
        }

        return $file->getI18nAttributeNames($definition->name, [$language])[$language] ?? $definition->name;
    }
}
