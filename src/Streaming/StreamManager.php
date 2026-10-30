<?php

declare(strict_types=1);

namespace Hirtz\Media\Video\Streaming;

use Closure;
use Exception;
use Hirtz\Media\Models\File;
use Hirtz\Skeleton\Helpers\Url;
use Hirtz\Skeleton\Web\ContentSecurityPolicy;
use Yii;
use yii\base\Component;
use yii\db\Expression;

class StreamManager extends Component
{
    final public const string ATTRIBUTE = 'stream';
    final public const string WEBHOOK_ROUTE = '/video-stream-webhook/index';

    /**
     * @var StreamProviderInterface|Closure(): StreamProviderInterface|class-string<StreamProviderInterface>|array{class: class-string<StreamProviderInterface>}
     */
    public StreamProviderInterface|Closure|string|array $provider;

    /**
     * `false` for an installation the provider cannot reach: an upload is left `pending` for `video-stream/submit`.
     */
    public bool $submitByUrl = true;

    public bool $webhookEnabled = true;

    private ?StreamProviderInterface $resolvedProvider = null;

    public static function getComponent(): self
    {
        /** @var self $manager */
        $manager = Yii::$app->get('videoStream');
        return $manager;
    }

    public function getProvider(): StreamProviderInterface
    {
        return $this->resolvedProvider ??= $this->provider instanceof Closure
            ? ($this->provider)()
            : ($this->provider instanceof StreamProviderInterface ? $this->provider : Yii::createObject($this->provider));
    }

    public function getState(File $file): ?StreamState
    {
        $state = $file->hasAttribute(self::ATTRIBUTE) ? $file->getAttribute(self::ATTRIBUTE) : null;
        return $state instanceof StreamState ? $state : null;
    }

    /**
     * Submits now by URL, or leaves the upload `pending` for the console.
     */
    public function schedule(File $file): StreamState
    {
        if ($this->submitByUrl) {
            return $this->submit($file);
        }

        $this->deleteRemote($file);

        $state = new StreamState($this->getProvider()->getName(), null);
        $this->saveState($file, $state);

        return $state;
    }

    /**
     * Replaces the file's remote video, if it has one.
     */
    public function submit(File $file): StreamState
    {
        $this->deleteRemote($file);

        $provider = $this->getProvider();
        $url = $this->submitByUrl ? Url::to($file->getUrl(), true) : null;

        try {
            $state = $provider->submit($file, $url, $this->getWebhookUrl());
        } catch (Exception $exception) {
            Yii::error($exception, __METHOD__);
            $state = StreamState::failed($provider->getName(), $exception->getMessage());
        }

        $this->saveState($file, $state);

        return $state;
    }

    public function refresh(File $file): ?StreamState
    {
        $state = $this->getState($file);

        if (!$state || !$this->isOwnState($state)) {
            return $state;
        }

        try {
            $fetched = $this->getProvider()->fetchState($state);
        } catch (Exception $exception) {
            Yii::error($exception, __METHOD__);
            return $state;
        }

        if ($fetched->toArray() !== $state->toArray()) {
            $this->saveState($file, $fetched);
        }

        return $fetched;
    }

    /**
     * Logs a provider error rather than raising it: the local file is what counts, and an orphan costs only storage.
     */
    public function deleteRemote(File $file): void
    {
        $state = $this->getState($file);

        if (!$state || !$this->isOwnState($state)) {
            return;
        }

        try {
            $this->getProvider()->delete($state);
        } catch (Exception $exception) {
            Yii::error($exception, __METHOD__);
        }
    }

    /**
     * Without validation, so a webhook or a command writes the state alone.
     */
    public function saveState(File $file, ?StreamState $state): void
    {
        $file->setAttribute(self::ATTRIBUTE, $state);
        $file->update(false, [$file->getCustomAttributesColumn()]);
    }

    public function isPlayable(File $file): bool
    {
        $state = $this->getState($file);
        return $state !== null && $state->isReady() && $this->isOwnState($state);
    }

    public function getPlaylistUrl(File $file): ?string
    {
        $state = $this->getState($file);
        return $state && $this->isPlayable($file) ? $this->getProvider()->getPlaylistUrl($state) : null;
    }

    public function getPosterUrl(File $file): ?string
    {
        $state = $this->getState($file);
        return $state && $this->isPlayable($file) ? $this->getProvider()->getPosterUrl($state) : null;
    }

    /**
     * Compared as JSON, since MariaDB stores the column as text and `JSON_EXTRACT()` answers a quoted string.
     */
    public function findFile(string $id): ?File
    {
        $column = File::instance()->getCustomAttributesColumn();
        $path = '$.' . self::ATTRIBUTE . '.id';

        $file = File::find()
            ->andWhere(new Expression("JSON_CONTAINS([[$column]], :id, '$path')", [
                ':id' => json_encode($id, JSON_THROW_ON_ERROR),
            ]))
            ->limit(1)
            ->one();

        return $file && ($state = $this->getState($file)) && $this->isOwnState($state) ? $file : null;
    }

    /**
     * Empty without webhooks; needs `urlManager.hostInfo` under the console.
     */
    public function getWebhookUrl(): string
    {
        return $this->webhookEnabled ? Url::to([self::WEBHOOK_ROUTE], true) : '';
    }

    /**
     * Adds to a directive the policy restricts (itself or through `default-src`), never one it leaves open.
     */
    public function registerContentSecurityPolicy(ContentSecurityPolicy $policy): void
    {
        $hosts = $this->getProvider()->getContentSecurityPolicy();

        $directives = [
            'media-src' => [...$hosts, 'blob:'],
            'connect-src' => $hosts,
            'img-src' => $hosts,
        ];

        foreach ($directives as $directive => $sources) {
            $current = $policy->directives[$directive] ?? $policy->directives['default-src'] ?? null;

            if ($current !== null) {
                $policy->setDirective($directive, array_values(array_unique([...$current, ...$sources])));
            }
        }
    }

    /**
     * A state another provider wrote is not this one's to fetch or delete.
     */
    protected function isOwnState(StreamState $state): bool
    {
        return $state->id !== null && $state->provider === $this->getProvider()->getName();
    }
}
