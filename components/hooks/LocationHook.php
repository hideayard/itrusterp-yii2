<?php

namespace app\components\hooks;

use app\models\Location;

class LocationHook
{
    public static function apply($model, array $input, bool $isUpdate): void
    {
        if ($model->parent_id) {
            $parent = Location::findUnscoped()->where(['id' => $model->parent_id])->one();
            if ($parent !== null) {
                $model->warehouse_id = $parent->warehouse_id;
                $model->full_name = $parent->full_name ? $parent->full_name . ' / ' . $model->name : $model->name;
            } else {
                $model->parent_id = null;
            }
        }

        if (!$model->parent_id) {
            $model->full_name = $model->name;
        }
    }
}
