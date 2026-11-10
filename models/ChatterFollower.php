<?php

namespace app\models;

use app\components\BaseModel;
use app\components\ValidationException;

/**
 * Follower chatter (polymorphic: followable_type/followable_id).
 */
class ChatterFollower extends BaseModel
{
    public static function tableName(): string
    {
        return 'chatter_followers';
    }

    public function beforeSave($insert)
    {
        if (!parent::beforeSave($insert)) {
            return false;
        }
        if ($insert && !$this->followed_at) {
            $this->followed_at = date('Y-m-d H:i:s');
        }

        // DB punya unique (followable_type, followable_id, partner_id) — kembalikan 422,
        // bukan error constraint 500.
        if ($insert && $this->partner_id !== null) {
            $exists = self::findUnscoped()->where([
                'followable_type' => $this->followable_type,
                'followable_id' => $this->followable_id,
                'partner_id' => $this->partner_id,
            ])->exists();
            if ($exists) {
                throw new ValidationException(['partner_id' => ['This partner already follows the record.']]);
            }
        }

        return true;
    }

    public function getPartner()
    {
        return $this->hasOne(Partner::class, ['id' => 'partner_id']);
    }

    public static function apiFields(): array
    {
        return ['id', 'followable_type', 'followable_id', 'partner_id', 'followed_at', 'created_at', 'updated_at'];
    }

    public static function apiCasts(): array
    {
        return [
            'followed_at' => 'iso',
            'created_at' => 'iso',
            'updated_at' => 'iso',
        ];
    }

    public static function apiRelations(): array
    {
        return ['partner' => 'partner'];
    }

    public static function apiRules(): array
    {
        return [
            'followable_type' => ['required', 'string', 'max:255'],
            'followable_id' => ['required', 'integer'],
            'partner_id' => ['integer', 'exists:partners_partners'],
        ];
    }
}
