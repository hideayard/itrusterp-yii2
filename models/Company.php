<?php

namespace app\models;

use app\components\BaseModel;

class Company extends BaseModel
{
    public static function tableName(): string
    {
        return 'companies';
    }

    public static function isSoftDelete(): bool
    {
        return true;
    }

    public static function isAllowedCompanyScoped(): bool
    {
        return true;
    }

    public static function isOwnershipScoped(): bool
    {
        return true;
    }

    public static function ownershipSources(): array
    {
        return ['creator_id'];
    }

    public function getParent()
    {
        return $this->hasOne(self::class, ['id' => 'parent_id']);
    }

    public function getBranches()
    {
        return $this->hasMany(self::class, ['parent_id' => 'id']);
    }

    public function getPartner()
    {
        return $this->hasOne(Partner::class, ['id' => 'partner_id']);
    }

    public function getCountry()
    {
        return $this->hasOne(Country::class, ['id' => 'country_id']);
    }

    public function getState()
    {
        return $this->hasOne(State::class, ['id' => 'state_id']);
    }

    public function getCurrency()
    {
        return $this->hasOne(Currency::class, ['id' => 'currency_id']);
    }

    public function getCreator()
    {
        return $this->hasOne(User::class, ['id' => 'creator_id']);
    }

    public static function apiFields(): array
    {
        return [
            'id', 'sort', 'name', 'tax_id', 'registration_number', 'email', 'phone', 'mobile',
            'city', 'zip', 'logo', 'color', 'is_active', 'founded_date', 'website',
            'country_id', 'state_id', 'currency_id', 'parent_id', 'partner_id', 'creator_id',
            'created_at', 'updated_at', 'deleted_at',
        ];
    }

    public static function apiCasts(): array
    {
        return [
            'sort' => 'int',
            'is_active' => 'bool',
            'founded_date' => 'iso',
            'created_at' => 'iso',
            'updated_at' => 'iso',
            'deleted_at' => 'iso',
        ];
    }

    public static function apiRelations(): array
    {
        return [
            'country' => 'country',
            'state' => 'state',
            'currency' => 'currency',
            'parent' => 'parent',
            'partner' => 'partner',
            'creator' => 'creator',
            'branches' => 'branches',
        ];
    }

    public static function apiRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'tax_id' => ['string'],
            'registration_number' => ['string'],
            'email' => ['email'],
            'phone' => ['string'],
            'mobile' => ['string'],
            'website' => ['url'],
            'color' => ['string'],
            'city' => ['string'],
            'zip' => ['string'],
            'logo' => ['string'],
            'is_active' => ['boolean'],
            'founded_date' => ['date'],
            'country_id' => ['integer', 'exists:countries'],
            'state_id' => ['integer', 'exists:states'],
            'currency_id' => ['integer', 'exists:currencies'],
            'parent_id' => ['integer'],
            'partner_id' => ['integer'],
            'sort' => ['integer'],
        ];
    }
}
