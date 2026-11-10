<?php

namespace app\models;

use app\components\BaseModel;

class PersonalAccessToken extends BaseModel
{
    public static function tableName(): string
    {
        return 'personal_access_tokens';
    }

    /**
     * Membuat token akses. Masa berlaku diambil dari env `AUREUS_TOKEN_TTL_DAYS`
     * (default 90 hari) supaya sesi tidak cepat habis.
     */
    public static function createToken(User $user, string $name = 'api-token', ?int $ttlDays = null): array
    {
        $plain = bin2hex(random_bytes(20));
        $days = $ttlDays ?? max(1, (int) (getenv('AUREUS_TOKEN_TTL_DAYS') ?: 90));

        $token = new self();
        $token->setAttributes([
            'tokenable_type' => User::LARAVEL_TYPE,
            'tokenable_id' => $user->id,
            'name' => $name,
            'token' => hash('sha256', $plain),
            'abilities' => null,
            'expires_at' => date('Y-m-d H:i:s', time() + $days * 86400),
        ], false);
        $token->save(false);

        return [$token, $plain];
    }
}
