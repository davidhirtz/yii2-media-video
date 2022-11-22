README
============================

Allows MP4 files to be used with [Yii 2](http://www.yiiframework.com/) extension [yii2-media](https://github.com/davidhirtz/yii2-media/) by David Hirtz.

INSTALLATION
-------------

The preferred way to install this extension is through [composer](http://getcomposer.org/download/).

```
composer require davidhirtz/yii2-media-video
```

SETUP
-------------

Use `davidhirtz\yii2\media\video\widgets\forms\FileVideoPreviewTrait` to display a video preview
in `davidhirtz\yii2\media\modules\admin\widgets\grid\FileActiveForm` or any related asset form.