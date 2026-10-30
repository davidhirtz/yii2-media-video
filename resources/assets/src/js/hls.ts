// hls.js takes over once `src` is set, by the server, a lazy loader moving `data-src` or an admin swap alike.
import type Hls from 'hls.js';
import type {ErrorData} from 'hls.js';

const MIME_TYPE = 'application/vnd.apple.mpegurl';
const attached = new WeakSet<HTMLVideoElement>();
const players = new WeakMap<HTMLVideoElement, Hls>();

// The local file is the master copy: a stream that does not load (a deleted video, a CDN outage) plays it instead.
const fallBack = ($video: HTMLVideoElement): void => {
    const fallback = $video.dataset.fallback;

    if (!fallback || !$video.hasAttribute('data-hls')) {
        return;
    }

    const resume = $video.autoplay || !$video.paused;

    players.get($video)?.destroy();
    $video.removeAttribute('data-hls');
    $video.removeAttribute('poster');
    $video.src = fallback;

    if (resume) {
        $video.play().catch(() => undefined);
    }
};

const attach = async ($video: HTMLVideoElement): Promise<void> => {
    const src = $video.getAttribute('src');

    if (!src || attached.has($video)) {
        return;
    }

    attached.add($video);
    $video.addEventListener('error', () => fallBack($video));

    if ($video.canPlayType(MIME_TYPE)) {
        return;
    }

    const {default: Hls} = await import('hls.js/light');

    if (!Hls.isSupported()) {
        return;
    }

    const lazy = $video.preload === 'none' && !$video.autoplay;
    const hls = new Hls({autoStartLoad: !lazy});

    players.set($video, hls);

    if (lazy) {
        $video.addEventListener('play', () => hls.startLoad(), {once: true});
    }

    hls.on(Hls.Events.ERROR, (_event: string, data: ErrorData) => {
        if (data.fatal) {
            fallBack($video);
        }
    });

    $video.removeAttribute('src');
    hls.loadSource(src);
    hls.attachMedia($video);
};

const scan = ($root: ParentNode): void => {
    $root.querySelectorAll<HTMLVideoElement>('video[data-hls]').forEach(attach);
};

new MutationObserver((mutations) => {
    for (const mutation of mutations) {
        if (mutation.type === 'attributes') {
            const $target = mutation.target;

            if ($target instanceof HTMLVideoElement && $target.hasAttribute('data-hls')) {
                attach($target);
            }

            continue;
        }

        mutation.addedNodes.forEach(($node) => {
            if ($node instanceof HTMLVideoElement && $node.hasAttribute('data-hls')) {
                attach($node);
            } else if ($node instanceof Element) {
                scan($node);
            }
        });
    }
}).observe(document.documentElement, {attributeFilter: ['src'], childList: true, subtree: true});

scan(document);
