<?php

namespace app\models;

use yii\db\ActiveRecord;

class PosPayment extends ActiveRecord
{
    public static function tableName(): string
    {
        return 'pos_payments';
    }

    public function getOrder()
    {
        return $this->hasOne(PosOrder::class, ['id' => 'order_id']);
    }

    public function getSession()
    {
        return $this->hasOne(PosSession::class, ['id' => 'session_id']);
    }

    public function getPaymentMethod()
    {
        return $this->hasOne(PosPaymentMethod::class, ['id' => 'payment_method_id']);
    }

    public function getPartner()
    {
        return $this->hasOne(Partner::class, ['id' => 'partner_id']);
    }

    public function getUser()
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }
}
