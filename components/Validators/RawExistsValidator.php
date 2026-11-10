<?php

namespace app\components\Validators;

use yii\db\Query;
use yii\validators\Validator;

class RawExistsValidator extends Validator
{
    public $table;
    public $column = 'id';

    public function validateAttribute($model, $attribute)
    {
        $value = $model->$attribute;
        if ($value === null || $value === '') {
            return;
        }
        if (!(new Query())->from($this->table)->where([$this->column => $value])->exists()) {
            $this->addError($model, $attribute, "The selected {$attribute} is invalid.");
        }
    }
}
