<?php

namespace app\controllers\v1;

use Yii;
use yii\web\Response;
use app\components\ApiController;

class ErrorController extends ApiController
{
    protected function publicActions(): array
    {
        return ['options'];
    }

    protected function verbs(): array
    {
        return ['options' => ['OPTIONS']];
    }

    public function actionOptions()
    {
        $response = Yii::$app->response;
        $this->applyCorsHeaders($response);
        $response->format = Response::FORMAT_RAW;
        $response->setStatusCode(204);
        $response->content = '';
        return null;
    }
}
