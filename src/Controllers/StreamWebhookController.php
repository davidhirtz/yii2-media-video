<?php

declare(strict_types=1);

namespace Hirtz\Media\Video\Controllers;

use Hirtz\Media\Video\Streaming\StreamManager;
use Hirtz\Skeleton\Web\Controller;
use Override;
use yii\base\Module;
use yii\filters\VerbFilter;
use yii\web\Response;

/**
 * Any id answers `204`, which neither tells a known one apart nor makes the provider drop the event.
 *
 * @extends Controller<Module>
 */
class StreamWebhookController extends Controller
{
    public $enableCsrfValidation = false;

    #[Override]
    public function behaviors(): array
    {
        return [
            ...parent::behaviors(),
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'index' => ['post'],
                ],
            ],
        ];
    }

    public function actionIndex(): Response
    {
        $manager = StreamManager::getComponent();

        if ($manager->webhookEnabled) {
            $id = $manager->getProvider()->getVideoIdFromWebhook($this->request);
            $file = $id !== null ? $manager->findFile($id) : null;

            if ($file) {
                $manager->refresh($file);
            }
        }

        $this->response->setStatusCode(204);
        return $this->response;
    }
}
