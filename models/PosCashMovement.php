<?php

namespace app\models;

use yii\db\ActiveRecord;

class PosCashMovement extends ActiveRecord
{
    public const TYPE_IN = 'in';
    public const TYPE_OUT = 'out';

    public static function tableName(): string
    {
        return 'pos_cash_movements';
    }

    public function getSession()
    {
        return $this->hasOne(PosSession::class, ['id' => 'session_id']);
    }

    public function getUser()
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }
}
