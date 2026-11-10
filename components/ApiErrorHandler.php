<?php

namespace app\components;

use Yii;
use yii\web\ErrorHandler;
use yii\web\Response;

class ApiErrorHandler extends ErrorHandler
{
    public function renderException($exception)
    {
        $response = Yii::$app->has('response') ? Yii::$app->getResponse() : new Response();
        $response->format = Response::FORMAT_JSON;

        if ($exception instanceof ValidationException) {
            $response->setStatusCode($exception->statusCode);
            $response->data = [
                'message' => $exception->getMessage(),
                'errors' => $exception->errors,
            ];
        } elseif ($exception instanceof \yii\web\NotFoundHttpException) {
            $response->setStatusCode(404);
            $response->data = ['message' => 'Resource not found.'];
        } elseif ($exception instanceof \yii\web\UnauthorizedHttpException) {
            $response->setStatusCode(401);
            $response->data = ['message' => 'Unauthenticated.'];
        } elseif ($exception instanceof \yii\web\ForbiddenHttpException) {
            $response->setStatusCode(403);
            $response->data = ['message' => 'This action is unauthorized.'];
        } elseif ($exception instanceof \yii\web\MethodNotAllowedHttpException) {
            $response->setStatusCode(405);
            $response->data = ['message' => 'The requested method is not allowed for this route.'];
        } elseif ($exception instanceof \yii\web\HttpException) {
            $response->setStatusCode($exception->statusCode);
            $response->data = ['message' => $exception->getMessage() ?: 'Server Error.'];
        } else {
            $response->setStatusCode(500);
            $response->data = ['message' => YII_DEBUG ? $exception->getMessage() : 'Server Error.'];
        }

        $response->send();
    }
}
