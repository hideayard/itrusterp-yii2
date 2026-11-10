<?php

namespace app\models;

use app\components\BaseModel;

class PaymentTerm extends BaseModel
{
    public static function tableName(): string
    {
        return 'accounts_payment_terms';
    }

    public static function isSoftDelete(): bool
    {
        return true;
    }

    public static function isCompanyScoped(): bool
    {
        return true;
    }

    public function getCompany()
    {
        return $this->hasOne(Company::class, ['id' => 'company_id']);
    }

    public function getCreator()
    {
        return $this->hasOne(User::class, ['id' => 'creator_id']);
    }

    public function getDueTerms()
    {
        return $this->hasMany(PaymentDueTerm::class, ['payment_id' => 'id']);
    }

    public static function apiFields(): array
    {
        return ['id', 'name', 'note', 'company_id', 'creator_id', 'created_at', 'updated_at', 'deleted_at'];
    }

    public static function apiCasts(): array
    {
        return ['created_at' => 'iso', 'updated_at' => 'iso', 'deleted_at' => 'iso'];
    }

    public static function apiRelations(): array
    {
        return ['company' => 'company', 'creator' => 'creator', 'due_terms' => 'dueTerms'];
    }

    public static function apiRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'company_id' => ['integer', 'exists:companies', 'company_allowed'],
            'note' => ['string'],
        ];
    }
}
