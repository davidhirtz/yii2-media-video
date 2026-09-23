# Upgrading to 3.0

## Requirements

- PHP `^8.3`
- `davidhirtz/yii2-media` `^3.0`, which brings `davidhirtz/yii2-skeleton` `^3.0`
- `james-heinrich/getid3` `^1.9`, unchanged

## Renames

### Namespaces

| v2                                   | v3                    |
|--------------------------------------|-----------------------|
| `davidhirtz\yii2\media\video`        | `Hirtz\Media\Video`   |
| `davidhirtz\yii2\media\video\tests`  | `Hirtz\Media\Video\Tests` |

Every directory under `src/` is StudlyCase (`models/behaviors` → `Models/Behaviors`, `modules/admin/widgets`
→ `Modules/Admin/Widgets`, `widgets` → `Widgets`).

### Classes

| v2                                                    | v3                                                   |
|-------------------------------------------------------|------------------------------------------------------|
| `widgets\Picture`                                     | `Widgets\Media`                                      |
| `modules\admin\widgets\forms\fields\FilePreview`      | `Modules\Admin\Widgets\Forms\Fields\FilePreviewField` |
| `modules\admin\widgets\grids\columns\Thumbnail`       | `Modules\Admin\Widgets\Grids\Columns\Thumbnail`      |
| `modules\admin\assets\VideoAsset`                     | `Assets\VideoAssetBundle`                            |
| `models\behaviors\FileVideoBehavior`                  | `Models\Behaviors\FileVideoBehavior`                 |

### Methods and properties

| v2                                             | v3                                                            |
|------------------------------------------------|---------------------------------------------------------------|
| `Picture::$videoOptions`                       | `Media::autoplay()` and `Media::video(Closure)`               |
| `Picture::$videoOptions['autoplay']`           | `Media::autoplay()`                                           |
| `Picture::$videoOptions['lazy']`               | `Media::lazyLoading()` (the media widget's own setter)        |
| `Picture::$videoOptions['lazyCssClass']`       | `Media::video(fn (Video $video) => $video->addClass(...))`    |
| `Picture::getVideoTag()`                       | `Media::renderVideo()` (protected)                            |
| `Picture::prepareVideoOptions()`, `addCssClass()` | removed                                                    |
| `FilePreview::run()`                           | `FilePreviewField::getContent()` (protected)                  |
| `Thumbnail::renderThumbnailContent()`          | `Thumbnail::renderContent()` (protected)                      |

### Assets and CSS

| v2                                                                   | v3                                                    |
|----------------------------------------------------------------------|-------------------------------------------------------|
| `@vendor/davidhirtz/yii2-media-video/src/modules/admin/assets/video` | `@media-video/../resources/video` (the alias is new)  |
| `.thumb.bg-dark` on the video thumbnail                              | `.img-thumbnail`                                      |

## Configuration

Nothing changes in `config/*.php`. `Bootstrap::$allowedVideoExtensions` keeps its name and default, and a
project that configures `modules.media.allowedExtensions` still has to list the video extensions itself.

A container definition that pointed at a v2 class is renamed with it:

```php
// v2
'container' => ['definitions' => [
    \davidhirtz\yii2\media\widgets\Picture::class => \app\widgets\Picture::class,
]],

// v3
'container' => ['definitions' => [
    \Hirtz\Media\Widgets\Media::class => \App\Widgets\Media::class,
]],
```

## Code changes

### The `Picture` widget is `Widgets\Media` with setters

The media bundle renamed its `Picture` widget to `Widgets\Media` and replaced its public option arrays with
fluent setters; the video subclass follows. `$videoOptions` is gone: `autoplay()` is the one switch, and the
`Hirtz\Skeleton\Html\Video` tag is handed to a `video()` closure for everything else.

```php
// v2
echo Picture::widget([
    'asset' => $asset,
    'videoOptions' => ['autoplay' => true, 'lazyCssClass' => 'lazyload'],
]);

// v3
echo Media::make()
    ->asset($asset)
    ->autoplay()
    ->video(fn (Video $video) => $video->addClass('lazyload'));
```

What the tag carries changed with it:

- `playsinline`, `loop` and `muted` are written only with `autoplay()`; v2 wrote `playsinline` always and
  derived `loop` and `muted` from `controls`.
- `data-src` instead of `src` follows the widget's `lazyLoading()` (on by default) rather than
  `autoplay && lazy`, and `preload` is written only without autoplay: `none` while lazy, `auto` otherwise.
- The classes of `imgOptions` are no longer copied onto the video; there is no `imgOptions`. Set the class in
  the `video()` closure.
- `aspectRatio(true)` writes the file's `aspect-ratio` on the video, as the media widget does on the image.

### A project subclass of the admin preview or thumbnail

Both admin classes extend the skeleton's widget layer now and render through a protected method returning
`string|Stringable` instead of a public `run(): string`:

```php
// v2
class FilePreview extends \davidhirtz\yii2\media\video\modules\admin\widgets\forms\fields\FilePreview
{
    public function run(): string { ... }
}

// v3
class FilePreviewField extends \Hirtz\Media\Video\Modules\Admin\Widgets\Forms\Fields\FilePreviewField
{
    #[Override]
    protected function getContent(): string|Stringable { ... }
}
```

The thumbnail column's hook is `renderContent()` in place of `renderThumbnailContent()`, and it reads its view
off `$this->view` rather than `Yii::$app->getView()`. A project asset bundle pointing at the old `@vendor`
path of `video.svg` points at `@media-video/../resources/video` or, simpler, registers `Assets\VideoAssetBundle`.

### `FileVideoBehavior`

Unchanged apart from the namespace. It is still attached by `Bootstrap` under the name `FileVideoBehavior`,
so a project detaching or replacing it by name needs no change.

## Data and schema

The bundle has no tables and no migrations, in v2 or v3. The v2 → v3 migrations of `davidhirtz/yii2-media`
cover the `file` table the behavior writes to.

## Removed

- `Picture::$videoOptions` and its `lazy` and `lazyCssClass` keys; see above.
- The Codeception test suite; the bundle tests with PHPUnit inside the monorepo.
