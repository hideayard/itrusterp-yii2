<?php

namespace app\components\hooks;

use Yii;

/**
 * Menulis kolom JSON multi-bahasa (spatie/laravel-translatable).
 *
 * Menerima nilai string (disimpan pada locale aktif) maupun objek
 * {"en": "...", "id": "..."} untuk mengubah beberapa bahasa sekaligus.
 */
class TranslatableHook
{
    public static function apply($model, array $input, bool $isUpdate): void
    {
        $attributes = method_exists($model, 'translatableAttributes') ? $model::translatableAttributes() : [];
        if ($attributes === []) {
            return;
        }

        $locale = 'en';
        if (Yii::$app->has('request') && !Yii::$app->request->getIsConsoleRequest()) {
            $locale = (string) (Yii::$app->request->get('locale') ?: 'en');
        }

        foreach ($attributes as $attribute) {
            if (!array_key_exists($attribute, $input)) {
                continue;
            }

            $value = $input[$attribute];

            if (is_array($value)) {
                $model->setAttribute($attribute, $value);
                continue;
            }

            $existing = $isUpdate ? $model->getOldAttribute($attribute) : null;
            $bag = is_array($existing) ? $existing : (json_decode((string) $existing, true) ?: []);
            if (!is_array($bag)) {
                $bag = [];
            }
            $bag[$locale] = $value;
            $model->setAttribute($attribute, $bag);
        }
    }
}
