<?php

declare(strict_types=1);

namespace Hirtz\Media\Video\Console\Controllers;

use Hirtz\Media\Models\File;
use Hirtz\Media\Models\Queries\FileQuery;
use Hirtz\Media\Video\Streaming\ProvisionableStreamProviderInterface;
use Hirtz\Media\Video\Streaming\StreamManager;
use Hirtz\Media\Video\Streaming\StreamState;
use Hirtz\Media\Video\Streaming\StreamStatus;
use Hirtz\Skeleton\Console\Controllers\Traits\UserIdentityTrait;
use Override;
use Yii;
use yii\base\NotSupportedException;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\helpers\Console;

/**
 * Submits videos to the streaming provider and fetches their state.
 */
class VideoStreamController extends Controller
{
    use UserIdentityTrait;

    /**
     * @var bool whether to submit every video without a stream, not only those left pending
     */
    public bool $all = false;

    /**
     * @var int|null the id of the one video to submit, whatever its state
     */
    public ?int $fileId = null;

    /**
     * @var string|null the provider account's key, used once and never stored
     */
    public ?string $accountKey = null;

    /**
     * @var string|null the name of the installation's scope at the provider, the application name by default
     */
    public ?string $name = null;

    #[Override]
    public function options($actionID): array
    {
        $options = [...parent::options($actionID), 'user'];

        return match ($actionID) {
            'submit' => [...$options, 'all', 'fileId'],
            'setup' => [...$options, 'accountKey', 'name'],
            default => $options,
        };
    }

    #[Override]
    public function beforeAction($action): bool
    {
        return parent::beforeAction($action) && $this->setUserIdentity();
    }

    /**
     * Submits the videos left pending; `--all` adds every video without a stream.
     *
     * @noinspection PhpUnused
     */
    public function actionSubmit(): int
    {
        $manager = StreamManager::getComponent();
        $count = 0;
        $failed = 0;

        /** @var File $file */
        foreach ($this->findVideos()->each() as $file) {
            if (!$file->isVideo() || !$this->isSubmittable($manager->getState($file))) {
                continue;
            }

            $state = $manager->submit($file);
            $state->status === StreamStatus::Failed ? $failed++ : $count++;

            if ($state->error !== null) {
                $this->stderr("#$file->id {$file->getFilename()}: $state->error" . PHP_EOL, Console::FG_RED);
            }
        }

        $this->stdout("$count videos were submitted" . PHP_EOL, Console::FG_GREEN);

        return $failed ? ExitCode::UNSPECIFIED_ERROR : ExitCode::OK;
    }

    /**
     * Fetches the state of every video still pending or processing, for an installation no webhook reaches.
     *
     * @noinspection PhpUnused
     */
    public function actionRefresh(): void
    {
        $manager = StreamManager::getComponent();
        $count = 0;

        /** @var File $file */
        foreach ($this->findVideos()->each() as $file) {
            $state = $manager->getState($file);

            if ($state?->id !== null && $state->status->isInProgress()) {
                $refreshed = $manager->refresh($file);
                $count += $refreshed?->status !== $state->status ? 1 : 0;
            }
        }

        $this->stdout("The state of $count videos changed" . PHP_EOL);
    }

    /**
     * Registers this installation's webhook URL with the provider (needs `urlManager.hostInfo`).
     *
     * @noinspection PhpUnused
     */
    public function actionWebhook(): int
    {
        $manager = StreamManager::getComponent();
        $url = $manager->getWebhookUrl();

        if ($url === '') {
            $this->stderr('Webhooks are disabled (`webhookEnabled`)' . PHP_EOL, Console::FG_RED);
            return ExitCode::CONFIG;
        }

        try {
            $manager->getProvider()->registerWebhook($url);
        } catch (NotSupportedException $exception) {
            $this->stderr($exception->getMessage() . PHP_EOL, Console::FG_RED);
            return ExitCode::UNAVAILABLE;
        }

        $this->stdout("Registered $url" . PHP_EOL, Console::FG_GREEN);
        return ExitCode::OK;
    }

    /**
     * Creates this installation's scope at the provider and prints the params for `config/params.php`.
     *
     * @noinspection PhpUnused
     */
    public function actionSetup(): int
    {
        $manager = StreamManager::getComponent();
        $provider = $manager->getProvider();

        if (!$provider instanceof ProvisionableStreamProviderInterface) {
            $this->stderr("The provider \"{$provider->getName()}\" cannot be set up from here" . PHP_EOL, Console::FG_RED);
            return ExitCode::UNAVAILABLE;
        }

        if (!$this->accountKey) {
            $this->stderr('The provider account key is missing (`--account-key`)' . PHP_EOL, Console::FG_RED);
            return ExitCode::USAGE;
        }

        $params = $provider->provision($this->accountKey, $this->name ?? Yii::$app->name, $manager->getWebhookUrl());

        foreach ($params as $name => $value) {
            $this->stdout("'$name' => '$value'," . PHP_EOL);
        }

        return ExitCode::OK;
    }

    protected function findVideos(): FileQuery
    {
        $query = File::find()->andWhere(['extension' => File::getModule()->videoExtensions]);
        return $this->fileId !== null ? $query->andWhere(['id' => $this->fileId]) : $query;
    }

    protected function isSubmittable(?StreamState $state): bool
    {
        if ($this->fileId !== null) {
            return true;
        }

        return $state ? $state->id === null && $state->status === StreamStatus::Pending : $this->all;
    }
}
