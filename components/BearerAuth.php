<?php

namespace app\components;

use Yii;
use yii\filters\auth\HttpBearerAuth;
use yii\web\UnauthorizedHttpException;

class BearerAuth extends HttpBearerAuth
{
    /**
     * Pada hosting dengan PHP CGI/suPHP, Apache membuang header `Authorization`
     * sebelum sampai ke PHP (dan `getallheaders()` tidak selalu memuatnya).
     * Bila web server meneruskannya lewat `SetEnv` / `[E=HTTP_AUTHORIZATION]`,
     * nilainya ada di `$_SERVER['REDIRECT_HTTP_AUTHORIZATION']` atau
     * `$_SERVER['HTTP_AUTHORIZATION']`. Di sini nilainya disuntikkan kembali ke
     * koleksi header request agar `HttpBearerAuth` bisa membacanya.
     */
    public function beforeAction($action)
    {
        $request = Yii::$app->getRequest();
        $headers = $request->getHeaders();

        if ($headers->get('Authorization') === null) {
            foreach (['REDIRECT_HTTP_AUTHORIZATION', 'HTTP_AUTHORIZATION'] as $key) {
                if (!empty($_SERVER[$key])) {
                    $headers->set('Authorization', (string) $_SERVER[$key]);
                    break;
                }
            }
        }

        if ($headers->get('Authorization') === null && function_exists('apache_request_headers')) {
            foreach ((array) apache_request_headers() as $name => $value) {
                if (strcasecmp((string) $name, 'Authorization') === 0) {
                    $headers->set('Authorization', (string) $value);
                    break;
                }
            }
        }

        return parent::beforeAction($action);
    }

    public function handleFailure($response)
    {
        throw new UnauthorizedHttpException('Unauthenticated.');
    }
}
