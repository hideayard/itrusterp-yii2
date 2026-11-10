<?php

namespace app\components\Validators;

use yii\db\Expression;
use yii\db\Query;
use yii\validators\Validator;

class RawUniqueValidator extends Validator
{
    public $table;

    public function validateAttribute($model, $attribute)
    {
        $value = $model->$attribute;
        if ($value === null || $value === '') {
            return;
        }
        [$table, $column] = array_pad(explode(',', (string) $this->table, 2), 2, $attribute);
        $query = (new Query())->from($table)->where(new Expression(
            "LOWER(`{$column}`) = :val",
            [':val' => strtolower((string) $value)]
        ));
        $pk = $model->getPrimaryKey();
        if ($pk !== null) {
            $query->andWhere(['<>', 'id', $pk]);
        }
        if ($query->exists()) {
            $this->addError($model, $attribute, "The {$attribute} has already been taken.");
        }
    }
}
