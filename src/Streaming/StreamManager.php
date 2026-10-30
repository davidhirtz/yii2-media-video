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

    public function isSetUp(): bool
    {
        $provider = $this->getProvider();
        return !$provider instanceof ProvisionableStreamProviderInterface || $provider->isProvisioned();
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

        $provider = $this->getProvider();
        $state = new StreamState($provider->getName(), null, scope: $provider->getScope());
        $this->saveState($file, $state);

        return $state;
    }

    /**
     * Replaces the file's remote video, if it has one.
     */
    public function submit(File $file): StreamState
    {
        $provider = $this->getProvider();

        // Built before the delete: a console without `urlManager.hostInfo` throws, and the stream it has stays playable.
        try {
            $url = $this->submitByUrl ? Url::to($file->getUrl(), true) : null;
            $webhookUrl = $this->getWebhookUrl();
        } catch (Exception $exception) {
            return StreamState::failed($provider->getName(), $exception->getMessage())->withScope($provider->getScope());
        }

        $this->deleteRemote($file);

        try {
            $state = $provider->submit($file, $url, $webhookUrl);
        } catch (Exception $exception) {
            Yii::error($exception, __METHOD__);
            $state = StreamState::failed($provider->getName(), $exception->getMessage());
        }

        $state = $state->withScope($provider->getScope());

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

        // Compared with the row, not the copy loaded before the request: a resubmit or a later webhook may have won.
        return File::getDb()->transaction(function () use ($file, $fetched): StreamState {
            $stored = $this->findStoredState($file, true);

            if ($stored === null || $stored->id !== $fetched->id || ($stored->isReady() && $fetched->status->isInProgress())) {
                $file->setAttribute(self::ATTRIBUTE, $stored);
                $file->setOldAttribute(self::ATTRIBUTE, $stored);

                return $stored ?? $fetched;
            }

            if ($fetched->toArray() !== $stored->toArray()) {
                $this->saveState($file, $fetched);
            }

            return $fetched;
        });
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
     * Writes the `stream` key alone, so a webhook never writes back what an editor saved meanwhile; no hooks, no trail.
     */
    public function saveState(File $file, ?StreamState $state): void
    {
        $column = $file->getCustomAttributesColumn();
        $path = '$.' . self::ATTRIBUTE;

        $expression = $state === null
            ? new Expression("JSON_REMOVE([[$column]], '$path')")
            : new Expression("JSON_SET(COALESCE([[$column]], '{}'), '$path', JSON_EXTRACT(:state, '$'))", [
                ':state' => json_encode($state, JSON_THROW_ON_ERROR),
            ]);

        File::updateAll([$column => $expression], ['id' => $file->id]);

        $file->setAttribute(self::ATTRIBUTE, $state);
        $file->setOldAttribute(self::ATTRIBUTE, $state);

        File::getModule()->invalidatePageCache();
    }

    /**
     * Read off the row rather than the record, which may have been loaded before the last write.
     */
    public function findStoredState(File $file, bool $forUpdate = false): ?StreamState
    {
        $column = $file->getCustomAttributesColumn();
        $path = '$.' . self::ATTRIBUTE;

        $sql = 'SELECT JSON_EXTRACT([[' . $column . "]], '$path') FROM " . File::tableName() . ' WHERE [[id]] = :id';
        $value = File::getDb()->createCommand($sql . ($forUpdate ? ' FOR UPDATE' : ''), [':id' => $file->id])->queryScalar();

        return is_string($value) ? StreamState::fromArray(json_decode($value, true)) : null;
    }

    /**
     * Not a playable stream: its remote video is deleted first, so the local file would play until the new one is ready.
     */
    public function canResubmit(File $file): bool
    {
        return $file->isVideo() && !$this->isPlayable($file);
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
     * Written by another provider or in another scope: a database copied from stage to production, for instance.
     */
    public function isForeignState(StreamState $state): bool
    {
        $provider = $this->getProvider();
        return $state->provider !== $provider->getName() || $state->scope !== $provider->getScope();
    }

    /**
     * A foreign state is not this installation's to fetch, delete or play.
     */
    protected function isOwnState(StreamState $state): bool
    {
        return $state->id !== null && !$this->isForeignState($state);
    }
}
