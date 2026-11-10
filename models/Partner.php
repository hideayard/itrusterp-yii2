<?php

namespace app\models;

use app\components\BaseModel;

class Partner extends BaseModel
{
    public const ACCOUNT_INDIVIDUAL = 'individual';
    public const ACCOUNT_COMPANY = 'company';
    public const ACCOUNT_ADDRESS = 'address';

    public static function tableName(): string
    {
        return 'partners_partners';
    }

    public static function isSoftDelete(): bool
    {
        return true;
    }

    public static function isCompanyScoped(): bool
    {
        return true;
    }

    public function getParent()
    {
        return $this->hasOne(self::class, ['id' => 'parent_id']);
    }

    public function getCountry()
    {
        return $this->hasOne(Country::class, ['id' => 'country_id']);
    }

    public function getState()
    {
        return $this->hasOne(State::class, ['id' => 'state_id']);
    }

    public function getTitle()
    {
        return $this->hasOne(PartnerTitle::class, ['id' => 'title_id']);
    }

    public function getCompany()
    {
        return $this->hasOne(Company::class, ['id' => 'company_id']);
    }

    public function getIndustry()
    {
        return $this->hasOne(PartnerIndustry::class, ['id' => 'industry_id']);
    }

    public function getUser()
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }

    public function getCreator()
    {
        return $this->hasOne(User::class, ['id' => 'creator_id']);
    }

    public function getAddresses()
    {
        return $this->hasMany(self::class, ['parent_id' => 'id'])
            ->andWhere(['account_type' => self::ACCOUNT_ADDRESS]);
    }

    public function getContacts()
    {
        return $this->hasMany(self::class, ['parent_id' => 'id'])
            ->andWhere(['<>', 'account_type', self::ACCOUNT_ADDRESS]);
    }

    public function getBankAccounts()
    {
        return $this->hasMany(PartnerBankAccount::class, ['partner_id' => 'id']);
    }

    public function getTags()
    {
        return $this->hasMany(PartnerTag::class, ['id' => 'tag_id'])
            ->viaTable('partners_partner_tag', ['partner_id' => 'id']);
    }

    public static function apiFields(): array
    {
        return [
            'id', 'account_type', 'sub_type', 'name', 'email', 'phone', 'mobile', 'avatar', 'avatar_url',
            'job_title', 'website', 'tax_id', 'company_registry', 'reference', 'is_active', 'color',
            'city', 'zip', 'country_id', 'state_id', 'parent_id', 'title_id', 'company_id', 'industry_id',
            'user_id', 'creator_id', 'created_at', 'updated_at', 'deleted_at',
        ];
    }

    public static function apiComputed(): array
    {
        return [
            'avatar_url' => fn ($m) => $m->avatar === null ? null : '/storage/' . ltrim((string) $m->avatar, '/'),
        ];
    }

    public static function apiCasts(): array
    {
        return [
            'is_active' => 'bool',
            'created_at' => 'iso',
            'updated_at' => 'iso',
            'deleted_at' => 'iso',
        ];
    }

    public static function apiRelations(): array
    {
        return [
            'parent' => 'parent',
            'country' => 'country',
            'state' => 'state',
            'title' => 'title',
            'company' => 'company',
            'industry' => 'industry',
            'user' => 'user',
            'creator' => 'creator',
            'addresses' => 'addresses',
            'contacts' => 'contacts',
            'bank_accounts' => 'bankAccounts',
            'tags' => 'tags',
        ];
    }

    public static function apiRules(): array
    {
        return [
            'account_type' => ['required', 'in:individual,company,address'],
            'sub_type' => ['string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['email', 'max:255'],
            'phone' => ['string', 'max:20'],
            'mobile' => ['string', 'max:20'],
            'avatar' => ['string', 'max:255'],
            'job_title' => ['string', 'max:255'],
            'website' => ['url', 'max:255'],
            'tax_id' => ['string', 'max:255'],
            'company_registry' => ['string', 'max:255'],
            'reference' => ['string', 'max:255'],
            'color' => ['string', 'max:7'],
            'street1' => ['string', 'max:255'],
            'street2' => ['string', 'max:255'],
            'city' => ['string', 'max:255'],
            'zip' => ['string', 'max:20'],
            'state_id' => ['integer', 'exists:states'],
            'country_id' => ['integer', 'exists:countries'],
            'parent_id' => ['integer', 'exists:partners_partners'],
            'title_id' => ['integer', 'exists:partners_titles'],
            'company_id' => ['integer', 'exists:companies', 'company_allowed'],
            'industry_id' => ['integer', 'exists:partners_industries'],
            'user_id' => ['integer', 'exists:users'],
            'is_active' => ['boolean'],
        ];
    }
}
