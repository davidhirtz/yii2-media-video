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

- Added `Hirtz\Media\video\Modules\Admin\Widgets\Grids\Columns\Thumbnail` to reflect changes in `yii2-media`

## 2.1.2 (Jan 7, 2024)

- Updated `Picture` widget

## 2.1.1 (Jan 7, 2024)

- Added `FileThumbnailColumn` to display a video icon as thumbnail
- Removed `FileVideoPreviewTrait` in favor of `Hirtz\Media\video\Modules\Admin\Widgets\Fields\FilePreview`
  which is automatically applied in package bootstrap
- Removed `Hirtz\Media\video\helpers\Video` in favor of an enhanced `Picture` widget

## 2.1.0 (Dec 20, 2023)

- Added Codeception test suite
- Added GitHub Actions CI workflow

## 2.0.1 (Nov 6, 2023)

- Changed namespaces for model interfaces to `Hirtz\Media\models\interfaces`
- Moved `Hirtz\Media\video\components\helpers\Video` to `Hirtz\Media\video\helpers\Video`
- Moved `Bootstrap` class to base package namespace for consistency

## v2.0.0 (Nov 3, 2023)

- Moved source code to `src` folder
- Added `Hirtz\Media\video\components\helpers\Video` HTML video helper tag
- Locked `davidhirtz/yii2-media` to version `2.0`

## v1.0.3 (Nov 2, 2023)

- Locked `davidhirtz/yii2-media` to version `^1.3`, upgrade to version 2.0 to use the latest version of this package