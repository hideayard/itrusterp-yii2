<?php

namespace app\components;

use yii\filters\auth\HttpBearerAuth;
use yii\web\UnauthorizedHttpException;

class BearerAuth extends HttpBearerAuth
{
    public function handleFailure($response)
    {
        throw new UnauthorizedHttpException('Unauthenticated.');
    }
}
