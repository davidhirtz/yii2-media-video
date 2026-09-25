## Unreleased

- Changed `Media::video()` to stack its closures, like `picture()` and `image()`

## 3.0.0 (September 23, 2026)

- Renamed the namespace `davidhirtz\yii2\media\video` to `Hirtz\Media\Video` and every directory under `src/`
  to StudlyCase; requires PHP 8.3 and `davidhirtz/yii2-media` 3.0
- Renamed `widgets\Picture` to `Widgets\Media`, which overrides the media `Hirtz\Media\Widgets\Media` widget
  in the container; removed `$videoOptions` and its `lazy` and `lazyCssClass` keys in favor of `autoplay()`
  and `video(Closure)`, which is handed the `Hirtz\Skeleton\Html\Video` tag
- Changed the video rendering: `playsinline`, `loop` and `muted` follow `autoplay()`, `data-src` follows
  `lazyLoading()`, `preload` is `none` or `auto` for a video that does not autoplay, `aspectRatio(true)`
  writes the file's ratio as `aspect-ratio` style, and the classes of the image are no longer copied onto the
  video
- Renamed `modules\admin\widgets\forms\fields\FilePreview` to
  `Modules\Admin\Widgets\Forms\Fields\FilePreviewField`, rendering the `<video>` through `getContent()`
  instead of `run()`
- Changed `Modules\Admin\Widgets\Grids\Columns\Thumbnail` to render through `renderContent()` instead of
  `renderThumbnailContent()`, with the `img-thumbnail` class instead of `thumb bg-dark`
- Renamed `modules\admin\assets\VideoAsset` to `Assets\VideoAssetBundle`; moved `video.svg` to
  `resources/video/`, published from the new `@media-video` alias
- Renamed `models\behaviors\FileVideoBehavior` to `Models\Behaviors\FileVideoBehavior`; `Bootstrap` still
  attaches it to every `Hirtz\Media\Models\File` and appends `Bootstrap::$allowedVideoExtensions` to
  `modules.media.allowedExtensions` unless the project configures its own list

## 2.1.7 (Aug 13, 2024)

- Added `lazyCssClass` option to `Picture::$videoOptions` to set a CSS class for lazy loading videos
- Enhanced `Picture::$videoOptions` to automatically apply CSS classes defined in `Picture::$imgOptions`

## 2.1.6 (Jul 12, 2024)

- Fixed an issue with `Picture::$videoOptions` not setting the `autoplay` attribute correctly

## 2.1.5 (Feb 1, 2024)

- Updated `Bootstrap` to make use of media modules `EVENT_INIT` event

## 2.1.4 (Jan 10, 2024)

- Fixed Rector (Issue #1)
- Removed `Picture::$lazyVideoLoading` in favor of `Picture::$options`

## 2.1.3 (Jan 8, 2024)

- Added `Hirtz\Media\Video\Modules\Admin\Widgets\Grids\Columns\Thumbnail` to reflect changes in `yii2-media`

## 2.1.2 (Jan 7, 2024)

- Updated `Picture` widget

## 2.1.1 (Jan 7, 2024)

- Added `FileThumbnailColumn` to display a video icon as thumbnail
- Removed `FileVideoPreviewTrait` in favor of `Hirtz\Media\Video\Modules\Admin\Widgets\Fields\FilePreview`
  which is automatically applied in package bootstrap
- Removed `Hirtz\Media\Video\helpers\Video` in favor of an enhanced `Picture` widget

## 2.1.0 (Dec 20, 2023)

- Added Codeception test suite
- Added GitHub Actions CI workflow

## 2.0.1 (Nov 6, 2023)

- Changed namespaces for model interfaces to `Hirtz\Media\Models\Interfaces`
- Moved `Hirtz\Media\Video\components\helpers\Video` to `Hirtz\Media\Video\helpers\Video`
- Moved `Bootstrap` class to base package namespace for consistency

## v2.0.0 (Nov 3, 2023)

- Moved source code to `src` folder
- Added `Hirtz\Media\Video\components\helpers\Video` HTML video helper tag
- Locked `davidhirtz/yii2-media` to version `2.0`

## v1.0.3 (Nov 2, 2023)

- Locked `davidhirtz/yii2-media` to version `^1.3`, upgrade to version 2.0 to use the latest version of this package