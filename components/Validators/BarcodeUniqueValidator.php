<?php

namespace app\components\Validators;

use yii\db\Expression;
use yii\db\Query;
use yii\validators\Validator;

class BarcodeUniqueValidator extends Validator
{
    public $owner = 'product';

    public function validateAttribute($model, $attribute)
    {
        $value = $model->$attribute;
        if ($value === null || $value === '') {
            return;
        }
        $table = $this->owner === 'packaging' ? 'products_packagings' : 'products_products';
        $condition = ['and', new Expression('LOWER(`barcode`) = :barcode', [':barcode' => strtolower((string) $value)])];
        if ($model->company_id === null) {
            $condition[] = ['company_id' => null];
        } else {
            $condition[] = ['company_id' => $model->company_id];
        }
        if ($model->id) {
            $condition[] = ['<>', 'id', $model->id];
        }
        if ((new Query())->from($table)->where($condition)->exists()) {
            $this->addError($model, $attribute, 'The barcode has already been taken.');
        }
    }
}
