// The skeleton's `FileUploadPrepareDetail`, declared here: its sources are not part of a Composer install.
type FileUploadPrepareDetail = {
    file: File,
    input: HTMLInputElement,
    fields: Promise<[string, Blob] | null>[],
};

const MAX_WIDTH = 640;
const TIMEOUT = 10000;

const waitFor = ($video: HTMLVideoElement, type: string): Promise<void> => new Promise((resolve, reject) => {
    const timeout = setTimeout(() => reject(new Error(`No ${type} event`)), TIMEOUT);

    $video.addEventListener(type, () => {
        clearTimeout(timeout);
        resolve();
    }, {once: true});

    $video.addEventListener('error', () => {
        clearTimeout(timeout);
        reject(new Error('The browser cannot decode the video'));
    }, {once: true});
});

const toBlob = ($canvas: HTMLCanvasElement): Promise<Blob | null> =>
    new Promise((resolve) => $canvas.toBlob(resolve, 'image/webp', 0.85));

// Drawn from the local file rather than the uploaded one, so it needs no request and works on any storage. A codec
// the browser cannot decode leaves the video without a preview, and the admin falls back to the video element.
const capture = async (file: File): Promise<Blob | null> => {
    const url = URL.createObjectURL(file);
    const $video = document.createElement('video');

    $video.muted = true;
    $video.playsInline = true;
    $video.preload = 'auto';

    try {
        const loaded = waitFor($video, 'loadeddata');
        $video.src = url;
        await loaded;

        // A second in, or a tenth of a short clip: the first frame is often black.
        const seeked = waitFor($video, 'seeked');
        $video.currentTime = Math.min(1, ($video.duration || 0) / 10);
        await seeked;

        const scale = Math.min(1, MAX_WIDTH / $video.videoWidth);
        const $canvas = document.createElement('canvas');
        $canvas.width = Math.round($video.videoWidth * scale);
        $canvas.height = Math.round($video.videoHeight * scale);
        $canvas.getContext('2d')?.drawImage($video, 0, 0, $canvas.width, $canvas.height);

        return $canvas.width && $canvas.height ? await toBlob($canvas) : null;
    } catch {
        return null;
    } finally {
        $video.removeAttribute('src');
        URL.revokeObjectURL(url);
    }
};

document.addEventListener('file-upload:prepare', (event) => {
    const {file, input, fields} = (event as CustomEvent<FileUploadPrepareDetail>).detail;
    const name = input.name.replace(/\[upload]$/, '[preview]');

    if (file.type.startsWith('video/') && name !== input.name) {
        fields.push(capture(file).then((blob) => blob ? [name, blob] : null));
    }
});
