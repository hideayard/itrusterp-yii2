<?php

namespace app\models;

use yii\db\ActiveRecord;

class PosSession extends ActiveRecord
{
    public const STATE_OPENING_CONTROL = 'opening_control';
    public const STATE_OPENED = 'opened';
    public const STATE_CLOSING_CONTROL = 'closing_control';
    public const STATE_CLOSED = 'closed';

    public static function tableName(): string
    {
        return 'pos_sessions';
    }

    public function getConfig()
    {
        return $this->hasOne(PosConfig::class, ['id' => 'config_id']);
    }

    public function getUser()
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }

    public function getClosedBy()
    {
        return $this->hasOne(User::class, ['id' => 'closed_by_id']);
    }

    public function getCurrency()
    {
        return $this->hasOne(Currency::class, ['id' => 'currency_id']);
    }

    public function getCompany()
    {
        return $this->hasOne(Company::class, ['id' => 'company_id']);
    }

    public function getOrders()
    {
        return $this->hasMany(PosOrder::class, ['session_id' => 'id']);
    }

    public function getCashMovements()
    {
        return $this->hasMany(PosCashMovement::class, ['session_id' => 'id']);
    }
}
