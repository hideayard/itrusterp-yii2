<?php

namespace app\models;

use yii\db\ActiveRecord;

class PosPaymentMethod extends ActiveRecord
{
    public const TYPE_CASH = 'cash';
    public const TYPE_BANK = 'bank';
    public const TYPE_PAY_LATER = 'pay_later';

    public static function tableName(): string
    {
        return 'pos_payment_methods';
    }

    public function getJournal()
    {
        return $this->hasOne(Journal::class, ['id' => 'journal_id']);
    }

    public function getCompany()
    {
        return $this->hasOne(Company::class, ['id' => 'company_id']);
    }
}
