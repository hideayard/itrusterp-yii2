<?php

namespace app\controllers\v1;

use Yii;
use app\components\ResourceController;
use app\components\ValidationException;
use app\models\PriceList;
use app\models\PriceRuleItem;
use yii\db\Query;

class PriceListController extends ResourceController
{
    public function actionCreate()
    {
        $cfg = $this->cfg();
        $this->resolveParentIfNested($cfg);
        $this->authorize('create', $cfg);
        $this->resolveExpand($cfg);

        [$input, $payload] = $this->parseBody($cfg);
        $this->validateItems($payload['items'] ?? null);

        $model = new PriceList();
        $this->loadAttributes($model, $input);
        $this->validateModel($model, $input, false);
        if (!$model->save(false)) {
            throw new ValidationException($model->getErrors());
        }
        $this->replaceItems($model, $payload['items'] ?? []);

        Yii::$app->response->setStatusCode(201);
        return [
            'data' => $this->serialize($model, $cfg, true),
            'message' => 'Price list created successfully.',
        ];
    }

    public function actionUpdate($id)
    {
        $cfg = $this->cfg();
        $this->resolveParentIfNested($cfg);
        $this->authorize('update', $cfg);
        $this->resolveExpand($cfg);
        $model = $this->findModel($cfg, $id);

        [$input, $payload] = $this->parseBody($cfg);
        $this->validateItems($payload['items'] ?? null);

        $this->loadAttributes($model, $input);
        $this->validateModel($model, $input, true);
        if (!$model->save(false)) {
            throw new ValidationException($model->getErrors());
        }
        if (array_key_exists('items', $payload)) {
            $this->replaceItems($model, $payload['items'] ?? []);
        }

        return [
            'data' => $this->serialize($model, $cfg, true),
            'message' => 'Price list updated successfully.',
        ];
    }

    private function validateItems($items): void
    {
        if ($items === null) {
            return;
        }
        if (!is_array($items)) {
            throw new ValidationException(['items' => ['The items field must be an array.']]);
        }
        $rules = [
            'id' => 'integer|exists:products_price_rule_items',
            'display_apply_to' => 'in:product,category',
            'type' => 'required|in:percentage,formula,fixed',
            'base' => 'in:list_price,standard_price,price_rules',
            'product_id' => 'integer|exists:products_products',
            'category_id' => 'integer|exists:products_categories',
            'base_price_list_id' => 'integer|exists:products_product_price_lists',
            'min_quantity' => 'number',
            'fixed_price' => 'number',
            'percent_price' => 'number',
            'price_discount' => 'number',
            'price_markup' => 'number',
            'price_round' => 'number',
            'price_surcharge' => 'number',
            'price_min_margin' => 'number',
            'price_max_margin' => 'number',
            'starts_at' => 'date',
            'ends_at' => 'date',
        ];
        $errors = [];
        foreach ($items as $i => $item) {
            if (!is_array($item)) {
                $errors["items.{$i}"][] = "The items.{$i} field must be an array.";
                continue;
            }
            foreach ($rules as $field => $spec) {
                $value = $item[$field] ?? null;
                $required = str_contains($spec, 'required');
                if ($value === null || $value === '') {
                    if ($required) {
                        $errors["items.{$i}.{$field}"][] = "The items.{$i}.{$field} field is required.";
                    }
                    continue;
                }
                foreach (explode('|', $spec) as $rule) {
                    [$name, $arg] = array_pad(explode(':', $rule, 2), 2, null);
                    if ($name === 'required') {
                        continue;
                    }
                    $key = "items.{$i}.{$field}";
                    if (in_array($name, ['integer', 'number'], true) && !is_numeric($value)) {
                        $errors[$key][] = "The {$key} field must be " . ($name === 'integer' ? 'an integer.' : 'a number.');
                    } elseif ($name === 'in' && !in_array($value, explode(',', (string) $arg), true)) {
                        $errors[$key][] = "The selected {$key} is invalid.";
                    } elseif ($name === 'exists' && !(new Query())->from((string) $arg)->where(['id' => $value])->exists()) {
                        $errors[$key][] = "The selected {$key} is invalid.";
                    } elseif ($name === 'date' && strtotime((string) $value) === false) {
                        $errors[$key][] = "The {$key} field must be a valid date.";
                    }
                }
            }
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }
    }

    private function replaceItems(PriceList $list, array $items): void
    {
        Yii::$app->db->createCommand()
            ->delete('products_price_rule_items', ['price_list_id' => $list->id])
            ->execute();
        foreach ($items as $item) {
            $row = new PriceRuleItem();
            $row->setAttribute('price_list_id', $list->id);
            foreach ($item as $k => $v) {
                if (in_array((string) $k, PriceRuleItem::writableAttributes(), true)) {
                    $row->setAttribute((string) $k, $v);
                }
            }
            $row->save(false);
        }
    }
}
