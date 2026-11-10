<?php

namespace app\models;

use app\components\BaseModel;

class PurchaseAgreement extends BaseModel
{
    public static function tableName(): string
    {
        return 'purchases_requisitions';
    }

    public static function isSoftDelete(): bool
    {
        return true;
    }

    public static function isCompanyScoped(): bool
    {
        return true;
    }

    public static function autoAssignCompany(): bool
    {
        return true;
    }

    public function getCurrency()
    {
        return $this->hasOne(Currency::class, ['id' => 'currency_id']);
    }

    public function getPartner()
    {
        return $this->hasOne(Partner::class, ['id' => 'partner_id']);
    }

    public function getUser()
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }

    public function getCompany()
    {
        return $this->hasOne(Company::class, ['id' => 'company_id']);
    }

    public function getCreator()
    {
        return $this->hasOne(User::class, ['id' => 'creator_id']);
    }

    public function getLines()
    {
        return $this->hasMany(PurchaseAgreementLine::class, ['requisition_id' => 'id']);
    }

    public static function apiFields(): array
    {
        return [
            'id', 'name', 'type', 'state', 'reference', 'starts_at', 'ends_at', 'description',
            'currency_id', 'partner_id', 'user_id', 'company_id', 'creator_id',
            'created_at', 'updated_at', 'deleted_at',
        ];
    }

    public static function apiCasts(): array
    {
        return [
            'starts_at' => 'iso',
            'ends_at' => 'iso',
            'created_at' => 'iso',
            'updated_at' => 'iso',
            'deleted_at' => 'iso',
        ];
    }

    public static function apiRelations(): array
    {
        return [
            'currency' => 'currency',
            'partner' => 'partner',
            'user' => 'user',
            'company' => 'company',
            'creator' => 'creator',
            'lines' => 'lines',
        ];
    }

    public static function apiRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:blanket_order,purchase_template'],
            'state' => ['in:draft,confirmed,closed,canceled'],
            'reference' => ['string', 'max:255'],
            'starts_at' => ['date'],
            'ends_at' => ['date'],
            'description' => ['string'],
            'currency_id' => ['required', 'integer', 'exists:currencies'],
            'partner_id' => ['required', 'integer', 'exists:partners_partners'],
            'user_id' => ['integer', 'exists:users'],
        ];
    }
}
