# yii2-media-video

Adds HTML video to [yii2-media](https://github.com/davidhirtz/yii2-media): a file with a video extension is
accepted by the upload, rendered as a `<video>` tag by the media widget on the frontend, and previewed with a
player in the admin. Its admin thumbnail is a frame the browser captures from the local file while it uploads
(`VideoPreviewAssetBundle`, no server software needed), and the video's own first frame when the browser could not
decode it. The dimensions of an uploaded video are read with
[getID3](https://github.com/JamesHeinrich/getID3). The bundle depends on `davidhirtz/yii2-media` and the
skeleton it brings along; it has no module and no migrations of its own.

## Installation

```bash
composer require davidhirtz/yii2-media-video
```

The bundle bootstraps itself through `extra.bootstrap` (`Hirtz\Media\Video\Bootstrap`). Nothing needs to run
afterwards: there are no migrations; `file/duration` reads the duration of videos uploaded before.

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

`Bootstrap::getDefaultConfig()` re-points four media classes to their video-aware subclasses through the DI
container. These are defaults merged under the application's configuration, so a definition the project declares
for one of them wins:

| Media class                                                       | Video subclass                                                       |
|-------------------------------------------------------------------|----------------------------------------------------------------------|
| `Hirtz\Media\Widgets\Media`                                       | `Hirtz\Media\Video\Widgets\Media`                                    |
| `Hirtz\Media\Modules\Admin\Widgets\Forms\Fields\FilePreviewField` | `Hirtz\Media\Video\Modules\Admin\Widgets\Forms\Fields\FilePreviewField` |
| `Hirtz\Media\Modules\Admin\Widgets\Grids\Columns\Thumbnail`       | `Hirtz\Media\Video\Modules\Admin\Widgets\Grids\Columns\Thumbnail`    |
| `Hirtz\Media\Console\Controllers\FileController`                 | `Hirtz\Media\Video\Console\Controllers\FileController`              |
| `Hirtz\Media\Modules\Admin\Widgets\Navs\FileActionDropdown`      | `Hirtz\Media\Video\Modules\Admin\Widgets\Navs\FileActionDropdown`   |

A project with a subclass of its own extends the video class instead, or declares its own definition in
`container.definitions` and takes over the video handling itself.

### Subtitles

Every video file has a translated `subtitles` attribute (`Models\CustomAttributes\SubtitlesCustomAttribute`,
appended through `modules.media.fileCustomAttributes`): one WebVTT file per content language, uploaded on the file
page by editors holding `File::AUTH_FILE`. A file must end in `.vtt` and begin with `WEBVTT`. The subtitles stay with
the file when its upload is replaced.

Over MCP, `get_record` answers each language's subtitles as their URL, and two tools beside the `file` resource
(`File::AUTH_FILE` only) read and write the text an agent translates: `get_subtitles` (`file`, `language`) answers the
WebVTT text, `set_subtitles` (`file`, `language`, `vtt`, `null` removes) stores it through the same validation as an
admin upload.

### Duration

Every video file has a read-only `duration` attribute (`Models\CustomAttributes\DurationCustomAttribute`) in whole
seconds, read from the upload by getID3 and shown as `H:MM:SS` in the admin; agents read it through `get_record`.
Videos stored before the bundle read it get it from `./yii file/duration`.

### Streaming

Adaptive video (HLS) comes from an external provider through the `videoStream` component
(`Streaming\StreamManager`). Without it nothing changes; with it every video is submitted to the provider after its
upload and played from the provider's playlist once encoded, the local file staying the master copy and the fallback.
A provider is a class implementing `Streaming\StreamProviderInterface`, usually its own bundle that configures the
component from its params:

```php
'components' => [
    'videoStream' => [
        'class' => \Hirtz\Media\Video\Streaming\StreamManager::class,
        'provider' => ['class' => \Vendor\StreamProvider::class],
    ],
],
```

| Property         | Default | Meaning                                                                                    |
|------------------|---------|--------------------------------------------------------------------------------------------|
| `provider`       |         | The provider: an instance, a class name, a configuration array or a closure answering one   |
| `submitByUrl`    | `true`  | The provider fetches the file by its URL; `false` leaves an upload pending for the console |
| `webhookEnabled` | `true`  | Passes the webhook URL to the provider; `false` where none reaches the installation        |

- The state is the read-only `stream` attribute of every video file (`Models\CustomAttributes\StreamCustomAttribute`):
  provider, remote id, status (`pending`, `processing`, `ready`, `failed`) and error. The admin shows the status,
  agents read it through `get_record`.
- A replaced upload deletes the old remote video and submits the new one; deleting the file deletes its remote
  video; a duplicate gets a remote video of its own. A failing provider fails the stream, never the upload.
- The provider calls `POST /video-stream/webhook` (no CSRF, no session, always `204`); the payload only names the
  video, whose state is then fetched from the provider.
- Console: `video-stream/submit` uploads the videos left pending (`--all` adds every video without a stream,
  `--file-id` resubmits one), `video-stream/refresh` fetches the state of every video still pending or processing (for
  an installation no webhook reaches), `video-stream/webhook` registers the webhook URL with the provider, and
  `video-stream/setup --account-key=…` creates the installation's own scope at a provider implementing
  `ProvisionableStreamProviderInterface` and saves its params to `config/params.php` (`--config` for another file);
  an installation already set up is replaced only after an interactive confirmation. All need `urlManager.hostInfo`.
- A provider implementing `ProvisionableStreamProviderInterface` that is not provisioned yet wires only
  `video-stream/setup` (`StreamManager::isSetUp()`); every other command refuses to run.
- The admin plays the stream in the file preview, uses the provider's poster as the grid thumbnail of a video without
  a preview, and offers "Resubmit stream" in the file's action menu.
- `Test\FakeStreamProvider` records every call and answers configurable states, for tests and a local admin.

`Widgets\Media` writes the playlist to `src` (or `data-src`) with a `data-hls` attribute and the provider's poster
once the stream is ready, and registers `Assets\HlsAssetBundle`: Safari plays the playlist natively, every other
browser through hls.js as soon as `src` is set, whoever sets it. The admin's content security policy admits the
provider's hosts where it restricts `media-src`, `connect-src` or `img-src`; a frontend policy restricting them adds
the hosts the provider's `getContentSecurityPolicy()` answers to all three, plus `blob:` to `media-src`.

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
- `subtitles(false)` leaves out the `<track>` the widget otherwise adds for each language whose subtitles are
  stored, labelled with the language's own name, `default` on the application language's.
- `video(Closure)` is handed the `Hirtz\Skeleton\Html\Video` tag before it renders, for a class or any other
  attribute the widget does not set itself, a `poster` over the stream's included.

## Admin

- `Modules\Admin\Widgets\Forms\Fields\FilePreviewField` shows a `<video controls>` on the file page instead of
  the image preview, with the subtitle tracks.
- `Modules\Admin\Widgets\Grids\Columns\Thumbnail` draws a play icon on a black `img-thumbnail` in the file
  grid, published by `Assets\VideoAssetBundle` from `resources/video/video.svg`.
- `Models\Behaviors\FileVideoBehavior` is attached to every `Hirtz\Media\Models\File` and reads the width and
  height of an uploaded video before validation; a file getID3 cannot analyze fails validation on `upload`.
