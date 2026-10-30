<?php

declare(strict_types=1);

namespace Hirtz\Media\Video\Console\Controllers;

use DateTimeImmutable;
use Exception;
use Hirtz\Media\Models\File;
use Hirtz\Media\Models\Queries\FileQuery;
use Hirtz\Media\Video\Streaming\ListableStreamProviderInterface;
use Hirtz\Media\Video\Streaming\ProvisionableStreamProviderInterface;
use Hirtz\Media\Video\Streaming\StreamManager;
use Hirtz\Media\Video\Streaming\StreamState;
use Hirtz\Media\Video\Streaming\StreamStatus;
use Hirtz\Skeleton\Console\Controllers\Traits\ConfigTrait;
use Hirtz\Skeleton\Console\Controllers\Traits\UserIdentityTrait;
use Override;
use Yii;
use yii\base\NotSupportedException;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\db\Expression;
use yii\helpers\Console;

/**
 * Submits videos to the streaming provider and fetches their state.
 */
class VideoStreamController extends Controller
{
    use ConfigTrait;
    use UserIdentityTrait;

    /**
     * @var bool whether to submit every video without a stream of this installation, a copied installation's too, not only those left pending
     */
    public bool $all = false;

    /**
     * @var bool whether to submit the videos whose stream failed again
     */
    public bool $failed = false;

    /**
     * @var int|null the id of the one video to submit, whatever its state
     */
    public ?int $fileId = null;

    /**
     * @var bool whether to delete the orphans at the provider
     */
    public bool $delete = false;

    /**
     * @var string|null the provider account's key, used once and never stored; `VIDEO_STREAM_ACCOUNT_KEY` or asked for when omitted, which keeps it out of the shell history
     */
    public ?string $accountKey = null;

    /**
     * @var string|null the name of the installation's scope at the provider, the application name by default
     */
    public ?string $name = null;

    /**
     * @var string the params file `setup` writes to
     */
    public string $config = '@root/config/params.php';

    #[Override]
    public function options($actionID): array
    {
        $options = [...parent::options($actionID), 'user'];

        return match ($actionID) {
            'submit' => [...$options, 'all', 'failed', 'fileId'],
            'setup' => [...$options, 'accountKey', 'name', 'config'],
            'orphans' => [...$options, 'delete'],
            default => $options,
        };
    }

    #[Override]
    public function beforeAction($action): bool
    {
        if (!in_array($action->id, ['index', 'setup'], true) && !StreamManager::getComponent()->isSetUp()) {
            $this->stderr('Streaming is not set up yet, run `video-stream/setup` first' . PHP_EOL, Console::FG_RED);
            return false;
        }

        return parent::beforeAction($action) && $this->setUserIdentity();
    }

    /**
     * Lists the commands.
     *
     * @noinspection PhpUnused
     */
    public function actionIndex(): int
    {
        $exitCode = Yii::$app->runAction('help/index', [$this->id]);
        return is_int($exitCode) ? $exitCode : ExitCode::OK;
    }

    /**
     * Submits the videos left pending; `--failed` adds those that failed, `--all` every video without a stream.
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

            // Every further submit would be refused as well; the video waits as `pending` for the next run.
            if ($state->id === null && $state->status === StreamStatus::Pending) {
                $this->stdout("$count videos were submitted" . PHP_EOL, Console::FG_GREEN);
                $this->stderr('The provider is busy' . ($state->error !== null ? " ($state->error)" : '') . ', run `video-stream/submit` again later for the rest' . PHP_EOL, Console::FG_YELLOW);
                return ExitCode::TEMPFAIL;
            }

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
     * Lists the videos at the provider no file holds; `--delete` deletes them.
     *
     * @noinspection PhpUnused
     */
    public function actionOrphans(): int
    {
        $manager = StreamManager::getComponent();
        $provider = $manager->getProvider();

        if (!$provider instanceof ListableStreamProviderInterface) {
            $this->stderr("The provider \"{$provider->getName()}\" cannot list its videos" . PHP_EOL, Console::FG_RED);
            return ExitCode::UNAVAILABLE;
        }

        $held = [];

        /** @var File $file */
        foreach ($this->findVideos()->each() as $file) {
            $state = $manager->getState($file);

            if ($state?->id !== null && !$manager->isForeignState($state)) {
                $held[$state->id] = true;
            }
        }

        // The whole list first: a page that failed must not read as videos no file holds.
        try {
            $videos = [...$provider->getVideos()];
        } catch (Exception $exception) {
            $this->stderr($exception->getMessage() . PHP_EOL, Console::FG_RED);
            return ExitCode::UNAVAILABLE;
        }

        // A submit saves the id after the provider created the video, so a recent one may just not be stored yet.
        $cutoff = new DateTimeImmutable('-1 hour');
        $orphans = [];

        foreach ($videos as $id => $createdAt) {
            if (!isset($held[$id]) && ($createdAt === null || $createdAt < $cutoff)) {
                $orphans[] = (string)$id;
                $this->stdout("$id" . ($createdAt ? ' (' . $createdAt->format('Y-m-d H:i') . ')' : '') . PHP_EOL);
            }
        }

        $count = count($orphans);
        $this->stdout("$count of " . count($videos) . ' videos at the provider are held by no file' . PHP_EOL);

        if (!$this->delete || !$count || ($this->interactive && !$this->confirm("Delete $count videos at the provider?", false))) {
            return ExitCode::OK;
        }

        foreach ($orphans as $id) {
            $provider->delete(new StreamState($provider->getName(), $id, scope: $provider->getScope()));
        }

        $this->stdout("$count videos were deleted" . PHP_EOL, Console::FG_GREEN);

        return ExitCode::OK;
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
     * Creates this installation's scope at the provider and saves its params to `config/params.php`.
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

        // A second run would create a second scope and orphan the first, so it is never unattended.
        if ($provider->isProvisioned() && !($this->interactive
                && $this->confirm("This installation is already set up at \"{$provider->getName()}\". Create a new scope and replace its params?", false))) {
            $this->stdout('This installation is already set up' . PHP_EOL);
            return ExitCode::OK;
        }

        $this->accountKey = $this->accountKey ?: (getenv('VIDEO_STREAM_ACCOUNT_KEY') ?: null);

        if (!$this->accountKey && $this->interactive) {
            $this->accountKey = $this->promptSecret('The provider account key:');
        }

        if (!$this->accountKey) {
            $this->stderr('The provider account key is missing (`--account-key` or `VIDEO_STREAM_ACCOUNT_KEY`)' . PHP_EOL, Console::FG_RED);
            return ExitCode::USAGE;
        }

        try {
            $params = $provider->provision($this->accountKey, $this->name ?? Yii::$app->name, $manager->getWebhookUrl());
        } catch (Exception $exception) {
            $this->stderr($exception->getMessage() . PHP_EOL, Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $this->setConfig($this->config, [
            ...$this->getConfig($this->config),
            ...$params,
        ], 'Saved ' . implode(', ', array_keys($params)) . " to $this->config");

        // A new scope holds no video yet, so every stream stored is another installation's: a copied database.
        if ($count = $this->countVideosWithStream()) {
            $this->stdout("$count videos were streamed by another installation, run `video-stream/submit --all` to stream them here" . PHP_EOL);
        }

        return ExitCode::OK;
    }

    /**
     * Without echo on a terminal, so the key is neither on screen nor in a scrollback.
     */
    protected function promptSecret(string $label): ?string
    {
        $isTerminal = stream_isatty(STDIN);
        $this->stdout("$label ");

        if ($isTerminal) {
            shell_exec('stty -echo');
        }

        $value = fgets(STDIN);

        if ($isTerminal) {
            shell_exec('stty echo');
            $this->stdout(PHP_EOL);
        }

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
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

        if ($state === null || StreamManager::getComponent()->isForeignState($state)) {
            return $this->all;
        }

        return ($state->id === null && $state->status === StreamStatus::Pending)
            || ($this->failed && $state->status === StreamStatus::Failed);
    }

    /**
     * Read off the column, since the `stream` attribute is not wired before the setup.
     */
    protected function countVideosWithStream(): int
    {
        $column = File::instance()->getCustomAttributesColumn();
        $path = '$.' . StreamManager::ATTRIBUTE;

        return (int)$this->findVideos()
            ->andWhere(new Expression("JSON_CONTAINS_PATH([[$column]], 'one', '$path')"))
            ->count();
    }
}
