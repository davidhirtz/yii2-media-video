<?php

declare(strict_types=1);

namespace Hirtz\Media\Video\Models\CustomAttributes;

use Hirtz\Media\Models\File;
use Hirtz\Skeleton\Html\Track;
use Hirtz\Skeleton\Models\CustomAttributes\UploadCustomAttribute;
use Locale;
use Override;
use Yii;
use yii\web\UploadedFile;

class SubtitlesCustomAttribute extends UploadCustomAttribute
{
    private const string BOM = "\u{FEFF}";

    public function __construct(string $name)
    {
        parent::__construct($name);

        $this->label = Yii::t('video', 'FILE_SUBTITLES_LABEL');
        $this->extensions = ['vtt'];
        $this->permission = File::AUTH_FILE;
        $this->translatable = true;
        $this->visible = static fn (File $file): bool => $file->isVideo();
    }

    /**
     * @return list<Track>
     */
    public static function getFileTracks(File $file): array
    {
        $tracks = [];

        foreach ($file->getCustomAttributeDefinitions() as $definition) {
            if ($definition instanceof self) {
                $tracks = [...$tracks, ...$definition->getTracks($file)];
            }
        }

        return $tracks;
    }

    /**
     * One track per language holding a file, never another language's file in its place.
     *
     * @return list<Track>
     */
    public function getTracks(File $file): array
    {
        if (!$this->isVisible($file)) {
            return [];
        }

        $tracks = [];

        foreach ($file->getI18nAttributeNames($this->name) as $language => $attribute) {
            $url = $this->getUrl($file, $attribute);

            if ($url === null) {
                continue;
            }

            $tracks[] = Track::make()
                ->kind('subtitles')
                ->src($url)
                ->srclang($language)
                ->label($this->getLanguageLabel($language))
                ->default($language === Yii::$app->language);
        }

        return $tracks;
    }

    protected function getLanguageLabel(string $language): string
    {
        $label = Locale::getDisplayLanguage($language, $language) ?: $language;
        return mb_strtoupper(mb_substr($label, 0, 1)) . mb_substr($label, 1);
    }

    /**
     * A renamed text file passes the extension check, and a browser ignores a malformed track without a word.
     */
    #[Override]
    public function validateUploadedFile(UploadedFile $upload): ?string
    {
        return parent::validateUploadedFile($upload)
            ?? ($this->isWebVtt($upload->tempName) ? null : Yii::t('video', 'FILE_SUBTITLES_WEBVTT_ERROR'));
    }

    protected function isWebVtt(string $path): bool
    {
        $header = @file_get_contents($path, length: 10);

        if ($header === false) {
            return false;
        }

        if (str_starts_with($header, self::BOM)) {
            $header = substr($header, strlen(self::BOM));
        }

        return str_starts_with($header, 'WEBVTT')
            && in_array(substr($header, 6, 1), ['', ' ', "\t", "\r", "\n"], true);
    }
}
