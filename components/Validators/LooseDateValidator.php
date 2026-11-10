<?php

namespace app\components\Validators;

use yii\validators\Validator;

class LooseDateValidator extends Validator
{
    public function validateAttribute($model, $attribute)
    {
        $value = $model->$attribute;
        if ($value === null || $value === '') {
            return;
        }
        if (strtotime((string) $value) === false) {
            $this->addError($model, $attribute, "The {$attribute} field must be a valid date.");
        }
    }
}
