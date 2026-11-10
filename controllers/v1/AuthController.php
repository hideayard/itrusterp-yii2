<?php

namespace app\controllers\v1;

use Yii;
use app\components\ApiController;
use app\components\ValidationException;
use app\models\PersonalAccessToken;
use app\models\User;

class AuthController extends ApiController
{
    protected function publicActions(): array
    {
        return ['login'];
    }

    protected function verbs(): array
    {
        return ['login' => ['POST'], 'logout' => ['POST']];
    }

    public function actionLogin()
    {
        $body = Yii::$app->request->getBodyParams();

        $identifier = $body['login'] ?? $body['email'] ?? null;
        $password = $body['password'] ?? null;

        $errors = [];
        if ($identifier === null || trim((string) $identifier) === '') {
            $errors['login'][] = 'The email or username field is required.';
        }
        if ($password === null || $password === '') {
            $errors['password'][] = 'The password field is required.';
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $identifier = trim((string) $identifier);
        $isEmail = strpos($identifier, '@') !== false;

        if ($isEmail && !filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
            throw new ValidationException(['login' => ['The email field must be a valid email address.']]);
        }

        $cache = Yii::$app->cache;
        $key = 'login_throttle:' . strtolower($identifier) . '|' . Yii::$app->request->getUserIP();
        $attempts = (int) $cache->get($key);
        if ($attempts >= 5) {
            Yii::$app->response->setStatusCode(429);
            return ['message' => 'Too many login attempts. Please try again in 60 seconds.'];
        }

        $query = User::findUnscoped();
        if ($isEmail) {
            $query->where(['email' => $identifier]);
        } else {
            $query->where(['username' => $identifier]);
        }
        $user = $query->one();

        if ($user === null || !password_verify((string) $password, (string) $user->password) || !$user->is_active) {
            $cache->set($key, $attempts + 1, 60);
            throw new ValidationException(
                ['login' => ['These credentials do not match our records.']],
                'These credentials do not match our records.'
            );
        }

        $cache->delete($key);

        [$token, $plain] = PersonalAccessToken::createToken($user);

        return [
            'message' => 'Login successful',
            'token' => $token->id . '|' . $plain,
            'token_type' => 'Bearer',
            'expires_at' => $token->expires_at,
            'expires_in' => max(0, strtotime((string) $token->expires_at) - time()),
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
            ],
        ];
    }

    public function actionLogout()
    {
        $tokenId = Yii::$app->access->tokenId;
        if ($tokenId !== null) {
            Yii::$app->db->createCommand()->delete('personal_access_tokens', ['id' => $tokenId])->execute();
        }
        return ['message' => 'Logout successful'];
    }
}
