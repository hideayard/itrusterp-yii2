<?php

namespace app\components\Validators;

use Yii;
use yii\validators\Validator;

class CompanyAllowedValidator extends Validator
{
    public function validateAttribute($model, $attribute)
    {
        $value = $model->$attribute;
        if ($value === null || $value === '') {
            return;
        }
        $access = Yii::$app->access;
        if ($access->bypassScopes()) {
            return;
        }
        if (in_array((int) $value, $access->allowedCompanyIds(), true)) {
            return;
        }
        $this->addError($model, $attribute, 'The selected company is not allowed.');
    }
}
