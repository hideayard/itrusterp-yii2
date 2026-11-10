<?php

namespace app\components\hooks;

use app\components\ValidationException;
use app\models\Department;

class DepartmentHook
{
    public static function apply($model, array $input, bool $isUpdate): void
    {
        if ($model->parent_id) {
            if ((string) $model->id === (string) $model->parent_id) {
                throw new ValidationException(['parent_id' => ['Circular reference detected in department hierarchy.']]);
            }
            $visited = [(string) $model->id];
            $current = $model->parent_id;
            while ($current) {
                if (in_array((string) $current, $visited, true)) {
                    throw new ValidationException(['parent_id' => ['Circular reference detected in department hierarchy.']]);
                }
                $visited[] = (string) $current;
                $parent = Department::findUnscoped()->where(['id' => $current])->one();
                if ($parent === null) {
                    break;
                }
                $current = $parent->parent_id;
            }
        }

        if ($model->parent_id) {
            $parent = Department::findUnscoped()->where(['id' => $model->parent_id])->one();
            if ($parent !== null) {
                $model->parent_path = ($parent->parent_path ?? '/') . $parent->id . '/';
                if (!$model->company_id) {
                    $model->company_id = $parent->company_id;
                }
            } else {
                $model->parent_path = '/';
                $model->parent_id = null;
            }
        } else {
            $model->parent_path = '/';
        }

        $model->complete_name = static::completeName($model);
    }

    private static function completeName($model): string
    {
        $names = [$model->name];
        $current = $model;
        while ($current->parent_id) {
            $parent = Department::findUnscoped()->where(['id' => $current->parent_id])->one();
            if ($parent === null) {
                break;
            }
            array_unshift($names, $parent->name);
            $current = $parent;
        }
        return implode(' / ', $names);
    }
}
