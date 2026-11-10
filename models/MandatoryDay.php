<?php

namespace app\models;

use app\components\BaseModel;

class MandatoryDay extends BaseModel
{
    public static function tableName(): string
    {
        return 'time_off_leave_mandatory_days';
    }

    public function getCompany()
    {
        return $this->hasOne(Company::class, ['id' => 'company_id']);
    }

    public function getCreator()
    {
        return $this->hasOne(User::class, ['id' => 'creator_id']);
    }

    public static function apiFields(): array
    {
        return ['id', 'company_id', 'creator_id', 'color', 'name', 'start_date', 'end_date', 'created_at', 'updated_at'];
    }

    public static function apiCasts(): array
    {
        return [
            'start_date' => 'iso',
            'end_date' => 'iso',
            'created_at' => 'iso',
            'updated_at' => 'iso',
        ];
    }

    public static function apiRelations(): array
    {
        return ['company' => 'company', 'creator' => 'creator'];
    }

    public static function apiRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date'],
            'color' => ['string', 'max:32'],
            'company_id' => ['integer', 'exists:companies', 'company_allowed'],
        ];
    }
}
