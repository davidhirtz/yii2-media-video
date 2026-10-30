<?php

declare(strict_types=1);

namespace Hirtz\Media\Video\Modules\Admin\Controllers;

use Hirtz\Media\Models\File;
use Hirtz\Media\Modules\Admin\Module;
use Hirtz\Media\Video\Streaming\StreamManager;
use Hirtz\Skeleton\Web\Controller;
use Override;
use Yii;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * @extends Controller<Module>
 */
class StreamController extends Controller
{
    #[Override]
    public function behaviors(): array
    {
        return [
            ...parent::behaviors(),
            'access' => [
                'class' => AccessControl::class,
                'rules' => [
                    [
                        'allow' => true,
                        'actions' => ['resubmit'],
                        'roles' => [File::AUTH_FILE],
                    ],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'resubmit' => ['post'],
                ],
            ],
        ];
    }

    public function actionResubmit(int $id): Response
    {
        $file = File::findOne($id);

        if (!$file?->isVideo()) {
            throw new NotFoundHttpException();
        }

        $state = StreamManager::getComponent()->schedule($file);

        if ($state->error !== null) {
            $this->error($state->error);
        } else {
            $this->success(Yii::t('video', 'FILE_STREAM_RESUBMIT_SUCCESS'));
        }

        return $this->redirect(['/admin/media/file/update', 'id' => $file->id]);
    }
}
