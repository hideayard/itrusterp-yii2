<?php

namespace app\models;

use app\components\BaseModel;

class PaymentDueTerm extends BaseModel
{
    public static function tableName(): string
    {
        return 'accounts_payment_due_terms';
    }

    public function getPaymentTerm()
    {
        return $this->hasOne(PaymentTerm::class, ['id' => 'payment_id']);
    }

    public function getCreator()
    {
        return $this->hasOne(User::class, ['id' => 'creator_id']);
    }

    public static function apiFields(): array
    {
        return ['id', 'value', 'value_amount', 'delay_type', 'nb_days', 'days_next_month', 'payment_id', 'creator_id', 'created_at', 'updated_at'];
    }

    public static function apiCasts(): array
    {
        return [
            'value_amount' => 'float',
            'nb_days' => 'int',
            'created_at' => 'iso',
            'updated_at' => 'iso',
        ];
    }

    public static function apiRelations(): array
    {
        return ['payment_term' => 'paymentTerm', 'creator' => 'creator'];
    }

    public static function apiRules(): array
    {
        return [
            'value' => ['required', 'in:percent,fixed'],
            'value_amount' => ['required', 'number', 'min_num:0', 'max_num:100'],
            'delay_type' => ['required', 'in:days_after,days_after_end_of_month,days_after_end_of_next_month,days_end_of_month_no_the'],
            'nb_days' => ['required', 'integer', 'min_num:0'],
            'days_next_month' => ['integer', 'min_num:1', 'max_num:31'],
        ];
    }
}
