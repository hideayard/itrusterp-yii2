<?php

namespace app\models;

use app\components\BaseModel;

class PartnerAddress extends Partner
{
    public static function apiFields(): array
    {
        return [
            'id', 'sub_type', 'name', 'email', 'phone', 'mobile', 'city', 'zip',
            'country_id', 'state_id', 'parent_id', 'creator_id',
            'created_at', 'updated_at', 'deleted_at',
        ];
    }

    public static function apiRelations(): array
    {
        return [
            'country' => 'country',
            'state' => 'state',
            'parent' => 'parent',
            'creator' => 'creator',
        ];
    }

    public static function apiRules(): array
    {
        return [
            'sub_type' => ['required', 'in:permanent,present,invoice,delivery,other'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['email', 'max:255'],
            'phone' => ['string', 'max:20'],
            'mobile' => ['string', 'max:20'],
            'city' => ['string', 'max:255'],
            'zip' => ['string', 'max:20'],
            'state_id' => ['integer', 'exists:states'],
            'country_id' => ['integer', 'exists:countries'],
        ];
    }
}
