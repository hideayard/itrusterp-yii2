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

        // Jalur cadangan: sebagian hosting (CGI/suPHP) membuang header
        // `Authorization`, jadi token boleh dikirim lewat `X-Auth-Token`
        // atau query `access_token` (dipakai frontend Astro).
        if ($headers->get('Authorization') === null) {
            $custom = $headers->get('X-Auth-Token');
            if ($custom === null && !empty($_SERVER['HTTP_X_AUTH_TOKEN'])) {
                $custom = $_SERVER['HTTP_X_AUTH_TOKEN'];
            }
            if ($custom === null && Yii::$app->request->get('access_token') !== null) {
                $custom = (string) Yii::$app->request->get('access_token');
            }
            if ($custom !== null && $custom !== '') {
                $value = preg_replace('/^Bearer\s+/i', '', (string) $custom);
                $headers->set('Authorization', 'Bearer ' . $value);
            }
        }

        return parent::beforeAction($action);
    }

    public function handleFailure($response)
    {
        throw new UnauthorizedHttpException('Unauthenticated.');
    }
}
