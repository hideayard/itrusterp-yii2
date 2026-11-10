<?php

namespace app\models;

use app\components\BaseModel;

class PartnerBankAccount extends BaseModel
{
    public static function tableName(): string
    {
        return 'partners_bank_accounts';
    }

    public static function isSoftDelete(): bool
    {
        return true;
    }

    public function getBank()
    {
        return $this->hasOne(Bank::class, ['id' => 'bank_id']);
    }

    public function getPartner()
    {
        return $this->hasOne(Partner::class, ['id' => 'partner_id']);
    }

    public static function apiFields(): array
    {
        return ['id', 'account_number', 'account_holder_name', 'can_send_money', 'bank_id', 'created_at', 'updated_at'];
    }

    public static function apiCasts(): array
    {
        return ['can_send_money' => 'bool', 'created_at' => 'iso', 'updated_at' => 'iso'];
    }

    public static function apiRelations(): array
    {
        return ['bank' => 'bank', 'creator' => 'creator', 'partner' => 'partner'];
    }

    public function getCreator()
    {
        return $this->hasOne(User::class, ['id' => 'creator_id']);
    }

    public static function apiRules(): array
    {
        return [
            'account_number' => ['required', 'string', 'max:255'],
            'account_holder_name' => ['required', 'string', 'max:255'],
            'can_send_money' => ['required', 'boolean'],
            'bank_id' => ['required', 'integer', 'exists:banks'],
            'partner_id' => ['integer', 'exists:partners_partners'],
            'is_active' => ['boolean'],
        ];
    }
}
