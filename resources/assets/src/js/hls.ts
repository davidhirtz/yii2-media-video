// hls.js takes over once `src` is set, by the server, a lazy loader moving `data-src` or an admin swap alike.
const MIME_TYPE = 'application/vnd.apple.mpegurl';
const attached = new WeakSet<HTMLVideoElement>();

const attach = async ($video: HTMLVideoElement): Promise<void> => {
    const src = $video.getAttribute('src');

    if (!src || attached.has($video) || $video.canPlayType(MIME_TYPE)) {
        return;
    }

    attached.add($video);

    const {default: Hls} = await import('hls.js/light');

    if (!Hls.isSupported()) {
        return;
    }

    const lazy = $video.preload === 'none' && !$video.autoplay;
    const hls = new Hls({autoStartLoad: !lazy});

    if (lazy) {
        $video.addEventListener('play', () => hls.startLoad(), {once: true});
    }

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
