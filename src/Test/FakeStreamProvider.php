<?php

declare(strict_types=1);

namespace Hirtz\Media\Video\Test;

use Hirtz\Media\Models\File;
use Hirtz\Media\Video\Streaming\ProvisionableStreamProviderInterface;
use Hirtz\Media\Video\Streaming\StreamState;
use Hirtz\Media\Video\Streaming\StreamStatus;
use Override;
use yii\base\BaseObject;
use yii\web\Request;

class FakeStreamProvider extends BaseObject implements ProvisionableStreamProviderInterface
{
    public string $name = 'fake';
    public string $scope = 'fake-library';
    public string $host = 'https://stream.example.com';
    public StreamStatus $submitStatus = StreamStatus::Processing;

    /**
     * What `fetchState()` answers, `null` keeps the state as it is.
     */
    public ?StreamStatus $status = null;

    public ?string $error = null;

    /**
     * `{id}` is replaced.
     */
    public ?string $playlistUrl = null;
    public bool $provisioned = true;

    /**
     * @var list<list<mixed>>
     */
    public array $calls = [];

    #[Override]
    public function getName(): string
    {
        return $this->name;
    }

    #[Override]
    public function submit(File $file, ?string $url, string $webhookUrl): StreamState
    {
        $this->calls[] = ['submit', $file->id, $url, $webhookUrl];

        $id = $this->submitStatus === StreamStatus::Pending ? null : 'fake-' . bin2hex(random_bytes(8));
        $error = $this->submitStatus === StreamStatus::Failed ? ($this->error ?? 'Submit failed') : null;

        return new StreamState($this->name, $id, $this->submitStatus, $error, ['title' => $file->getFilename()]);
    }

    #[Override]
    public function fetchState(StreamState $state): StreamState
    {
        $this->calls[] = ['fetchState', $state->id];
        return $this->status ? $state->withStatus($this->status, $this->error) : $state;
    }

    #[Override]
    public function delete(StreamState $state): void
    {
        $this->calls[] = ['delete', $state->id];
    }

    #[Override]
    public function getPlaylistUrl(StreamState $state): string
    {
        return str_replace('{id}', (string)$state->id, $this->playlistUrl ?? "$this->host/{id}/playlist.m3u8");
    }

    #[Override]
    public function getPosterUrl(StreamState $state): ?string
    {
        return "$this->host/$state->id/thumbnail.jpg";
    }

    #[Override]
    public function getVideoIdFromWebhook(Request $request): ?string
    {
        $id = $request->post('id');
        return is_string($id) && $id !== '' ? $id : null;
    }

    #[Override]
    public function registerWebhook(string $url): void
    {
        $this->calls[] = ['registerWebhook', $url];
    }

    #[Override]
    public function getContentSecurityPolicy(): array
    {
        return [$this->host];
    }

    #[Override]
    public function getScope(): string
    {
        return $this->scope;
    }

    #[Override]
    public function isProvisioned(): bool
    {
        return $this->provisioned;
    }

    #[Override]
    public function provision(string $accountKey, string $name, string $webhookUrl): array
    {
        $this->calls[] = ['provision', $accountKey, $name, $webhookUrl];
        return ['fakeLibraryId' => '1', 'fakeApiKey' => 'key'];
    }

    /**
     * @return list<list<mixed>>
     */
    public function getCalls(string $method): array
    {
        return array_values(array_filter($this->calls, static fn (array $call): bool => $call[0] === $method));
    }
}
