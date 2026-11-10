<?php

namespace app\models;

use yii\db\ActiveRecord;

class PosOrder extends ActiveRecord
{
    public const STATE_DRAFT = 'draft';
    public const STATE_PAID = 'paid';
    public const STATE_DONE = 'done';
    public const STATE_INVOICED = 'invoiced';
    public const STATE_CANCELED = 'canceled';

    public static function tableName(): string
    {
        return 'pos_orders';
    }

    public function getSession()
    {
        return $this->hasOne(PosSession::class, ['id' => 'session_id']);
    }

    public function getConfig()
    {
        return $this->hasOne(PosConfig::class, ['id' => 'config_id']);
    }

    public function getPartner()
    {
        return $this->hasOne(Partner::class, ['id' => 'partner_id']);
    }

    public function getUser()
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }

    public function getCurrency()
    {
        return $this->hasOne(Currency::class, ['id' => 'currency_id']);
    }

    public function getLines()
    {
        return $this->hasMany(PosOrderLine::class, ['order_id' => 'id']);
    }

    public function getPayments()
    {
        return $this->hasMany(PosPayment::class, ['order_id' => 'id']);
    }
}
