# yii2-media-video

Adds HTML video to [yii2-media](https://github.com/davidhirtz/yii2-media): a file with a video extension is
accepted by the upload, rendered as a `<video>` tag by the media widget on the frontend, and previewed with a
player and a placeholder thumbnail in the admin. The dimensions of an uploaded video are read with
[getID3](https://github.com/JamesHeinrich/getID3). The bundle depends on `davidhirtz/yii2-media` and the
skeleton it brings along; it has no module, no messages and no migrations of its own.

## Installation

```bash
composer require davidhirtz/yii2-media-video
```

The bundle bootstraps itself through `extra.bootstrap` (`Hirtz\Media\Video\Bootstrap`). Nothing needs to run
afterwards: there are no migrations, and no console commands.

## Configuration

There is no module. `Bootstrap` carries the one setting. Yii builds the extension bootstrap through
`Yii::createObject()`, so a container definition for `Hirtz\Media\Video\Bootstrap` configures it:

| Property                             | Default                   | Meaning                                                                        |
|--------------------------------------|---------------------------|--------------------------------------------------------------------------------|
| `Bootstrap::$allowedVideoExtensions` | `['mp4', 'webm', 'ogg']`  | Extensions appended to `modules.media.allowedExtensions` when the project sets none |

```php
'container' => ['definitions' => [
    \Hirtz\Media\Video\Bootstrap::class => ['allowedVideoExtensions' => ['mp4', 'webm']],
]],
```

### Allowed extensions

`Bootstrap` appends `$allowedVideoExtensions` to `Hirtz\Media\Module::$allowedExtensions` when the media
module is initialized, but only while the project does **not** configure `modules.media.allowedExtensions`
itself. A project setting its own list names the video extensions there:

```php
'modules' => [
    'media' => [
        'allowedExtensions' => ['jpg', 'jpeg', 'png', 'svg', 'webp', 'mp4', 'webm'],
    ],
],
```

### Container overrides

`Bootstrap` re-points three media classes to their video-aware subclasses through the DI container, each
only when the project has not registered a definition for that class already:

| Media class                                                       | Video subclass                                                       |
|-------------------------------------------------------------------|----------------------------------------------------------------------|
| `Hirtz\Media\Widgets\Media`                                       | `Hirtz\Media\Video\Widgets\Media`                                    |
| `Hirtz\Media\Modules\Admin\Widgets\Forms\Fields\FilePreviewField` | `Hirtz\Media\Video\Modules\Admin\Widgets\Forms\Fields\FilePreviewField` |
| `Hirtz\Media\Modules\Admin\Widgets\Grids\Columns\Thumbnail`       | `Hirtz\Media\Video\Modules\Admin\Widgets\Grids\Columns\Thumbnail`    |

A project with a subclass of its own extends the video class instead, or registers its own definition in
`container.definitions` before the bootstrap runs and takes over the video handling itself.

## Rendering a video

`Widgets\Media` renders a `<video>` for an asset whose file answers `isVideo()` and falls back to the media
widget's picture rendering otherwise, so the frontend calls the media widget as before:

```php
use Hirtz\Media\Widgets\Media;

echo Media::make()
    ->asset($asset)
    ->autoplay()
    ->video(fn (Video $video) => $video->addClass('lazyload'));
```

- `autoplay()` sets `autoplay`, `playsinline`, `loop` and `muted` together; without it the tag carries
  `controls` and `preload` (`none` while `lazyLoading()` is on, `auto` otherwise) and none of the four.
- `lazyLoading()` (on by default) writes the URL to `data-src` instead of `src`, for a loader script to pick up.
- `aspectRatio(true)` writes the file's width and height as an `aspect-ratio` style, as it does for an image.
- `video(Closure)` is handed the `Hirtz\Skeleton\Html\Video` tag before it renders, for a class or any other
  attribute the widget does not set itself.

## Admin

- `Modules\Admin\Widgets\Forms\Fields\FilePreviewField` shows a `<video controls>` on the file page instead of
  the image preview.
- `Modules\Admin\Widgets\Grids\Columns\Thumbnail` draws a play icon on a black `img-thumbnail` in the file
  grid, published by `Assets\VideoAssetBundle` from `resources/video/video.svg`.
- `Models\Behaviors\FileVideoBehavior` is attached to every `Hirtz\Media\Models\File` and reads the width and
  height of an uploaded video before validation; a file getID3 cannot analyze fails validation on `upload`.
