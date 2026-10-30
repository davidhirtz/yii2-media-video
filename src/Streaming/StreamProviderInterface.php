<?php

declare(strict_types=1);

namespace Hirtz\Media\Video\Streaming;

use Hirtz\Media\Models\File;
use yii\base\NotSupportedException;
use yii\web\Request;

/**
 * An API error answers a `failed` state or is logged, never an exception through a save.
 */
interface StreamProviderInterface
{
    public function getName(): string;

    /**
     * The provider's own name, shown to editors.
     */
    public function getLabel(): string;

    /**
     * What a video id is unique within (a library, a project): a state from another scope was copied from another
     * installation and is not this one's to play, fetch or delete.
     */
    public function getScope(): string;

    /**
     * The provider fetches `$url` when given, else the file's bytes are uploaded; `$webhookUrl` is empty without
     * webhooks. A provider refusing for now (a rate limit) answers `pending` without an id, left for the next submit.
     */
    public function submit(File $file, ?string $url, string $webhookUrl): StreamState;

    public function fetchState(StreamState $state): StreamState;

    public function delete(StreamState $state): void;

    public function getPlaylistUrl(StreamState $state): string;

    public function getPosterUrl(StreamState $state): ?string;

    /**
     * The video in the provider's own backend, for an editor holding an account there.
     */
    public function getDashboardUrl(StreamState $state): ?string;

    public function getVideoIdFromWebhook(Request $request): ?string;

    /**
     * @throws NotSupportedException
     */
    public function registerWebhook(string $url): void;

    /**
     * @return list<string> the hosts `media-src`, `connect-src` and `img-src` need
     */
    public function getContentSecurityPolicy(): array;
}
