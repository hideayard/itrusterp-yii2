<?php

namespace app\components;

use Yii;
use yii\filters\Cors;
use yii\filters\VerbFilter;
use yii\filters\auth\HttpBearerAuth;
use yii\web\Controller;
use yii\web\Response;
use yii\web\UnauthorizedHttpException;

abstract class ApiController extends Controller
{
    public $enableCsrfValidation = false;

    protected array $expand = [];

    public function behaviors(): array
    {
        return [
            'cors' => [
                'class' => Cors::class,
                'cors' => [
                    'Origin' => Yii::$app->params['corsOrigins'],
                    'Access-Control-Request-Method' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
                    'Access-Control-Request-Headers' => ['*'],
                    'Access-Control-Max-Age' => 86400,
                ],
            ],
            'authenticator' => [
                'class' => BearerAuth::class,
                'except' => $this->publicActions(),
            ],
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => $this->verbs(),
            ],
        ];
    }

    protected function publicActions(): array
    {
        return [];
    }

    protected function verbs(): array
    {
        return [
            'index' => ['GET'],
            'view' => ['GET'],
            'create' => ['POST'],
            'update' => ['PUT', 'PATCH'],
            'delete' => ['DELETE'],
            'restore' => ['POST'],
            'force-delete' => ['DELETE'],
        ];
    }

    public function beforeAction($action)
    {
        if (Yii::$app->request->getIsOptions()) {
            $response = Yii::$app->getResponse();
            $this->applyCorsHeaders($response);
            $response->format = Response::FORMAT_RAW;
            $response->setStatusCode(204);
            $response->content = '';
            return false;
        }

        if (!parent::beforeAction($action)) {
            return false;
        }

        if (Yii::$app->user->getIsGuest()
            && !in_array($action->id, $this->publicActions(), true)
        ) {
            throw new UnauthorizedHttpException('Unauthenticated.');
        }

        return true;
    }

    protected function applyCorsHeaders(Response $response): void
    {
        $origin = Yii::$app->request->getHeaders()->get('Origin');
        if ($origin !== false && in_array($origin, Yii::$app->params['corsOrigins'], true)) {
            $response->getHeaders()->set('Access-Control-Allow-Origin', $origin);
        }
        $response->getHeaders()->set('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS');
        $response->getHeaders()->set('Access-Control-Allow-Headers', '*');
        $response->getHeaders()->set('Access-Control-Max-Age', '86400');
    }
}
