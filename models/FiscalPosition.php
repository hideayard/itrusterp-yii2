<?php

namespace app\models;

use app\components\BaseModel;

class FiscalPosition extends BaseModel
{
    public static function tableName(): string
    {
        return 'accounts_fiscal_positions';
    }

    public static function isCompanyScoped(): bool
    {
        return true;
    }

    public function beforeSave($insert)
    {
        if (!parent::beforeSave($insert)) {
            return false;
        }
        if ($insert) {
            $this->auto_reply = $this->auto_reply ?? 0;
            $this->vat_required = $this->vat_required ?? 0;
            $this->sort = $this->sort ?? 0;
        }
        return true;
    }

    public function getCompany()
    {
        return $this->hasOne(Company::class, ['id' => 'company_id']);
    }

    public function getCountry()
    {
        return $this->hasOne(Country::class, ['id' => 'country_id']);
    }

    public function getCountryGroup()
    {
        return $this->hasOne(Country::class, ['id' => 'country_group_id']);
    }

    public function getCreator()
    {
        return $this->hasOne(User::class, ['id' => 'creator_id']);
    }

    public static function apiFields(): array
    {
        return [
            'id', 'company_id', 'country_id', 'country_group_id', 'creator_id', 'name',
            'zip_from', 'zip_to', 'foreign_vat', 'notes', 'auto_reply', 'vat_required', 'sort',
            'created_at', 'updated_at',
        ];
    }

    public static function apiCasts(): array
    {
        return [
            'auto_reply' => 'bool',
            'vat_required' => 'bool',
            'sort' => 'int',
            'created_at' => 'iso',
            'updated_at' => 'iso',
        ];
    }

    public static function apiRelations(): array
    {
        return [
            'company' => 'company',
            'country' => 'country',
            'country_group' => 'countryGroup',
            'creator' => 'creator',
        ];
    }

    public static function apiRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'company_id' => ['required', 'integer', 'exists:companies', 'company_allowed'],
            'country_id' => ['integer', 'exists:countries'],
            'country_group_id' => ['integer', 'exists:countries'],
            'zip_from' => ['string', 'max:10'],
            'zip_to' => ['string', 'max:10'],
            'notes' => ['string'],
            'auto_reply' => ['boolean'],
            'vat_required' => ['boolean'],
            'foreign_vat' => ['string', 'max:255'],
            'sort' => ['integer'],
        ];
    }
}
