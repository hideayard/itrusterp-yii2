<?php

namespace app\components\Validators;

use yii\db\Query;
use yii\validators\Validator;

class ArrayValidator extends Validator
{
    public $itemType = 'integer';
    public $table;

    public function validateAttribute($model, $attribute)
    {
        $value = $model->$attribute;
        if ($value === null) {
            return;
        }
        if (!is_array($value)) {
            $this->addError($model, $attribute, "The {$attribute} field must be an array.");
            return;
        }
        foreach ($value as $i => $item) {
            if ($this->itemType === 'integer') {
                if (!is_numeric($item) || (string) (int) $item !== (string) $item) {
                    $this->addError($model, $attribute, "The {$attribute}.{$i} field must be an integer.");
                    return;
                }
                if ($this->table !== null
                    && !(new Query())->from($this->table)->where(['id' => (int) $item])->exists()
                ) {
                    $this->addError($model, $attribute, "The selected {$attribute}.{$i} is invalid.");
                    return;
                }
            } elseif ($this->itemType === 'string') {
                if (!is_string($item)) {
                    $this->addError($model, $attribute, "The {$attribute}.{$i} field must be a string.");
                    return;
                }
            }
        }
    }
}
