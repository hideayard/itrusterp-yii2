<?php

namespace app\controllers\v1;

use Yii;
use app\components\ResourceController;
use app\components\UsageRegistry;
use app\components\ValidationException;
use app\models\ProductAttribute;
use app\models\ProductAttributeValue;

class ProductAttributeController extends ResourceController
{
    public function actionCreate()
    {
        $cfg = $this->cfg();
        [$parent] = $this->resolveParentIfNested($cfg);
        $this->authorize('create', $cfg);
        $this->resolveExpand($cfg);

        if (!$parent->is_configurable && UsageRegistry::isProductInUse($parent->id)) {
            throw new ValidationException(
                [],
                "The product {$parent->name} is in use and cannot be modified."
            );
        }

        [$input, $payload] = $this->parseBody($cfg);
        $this->validatePayload($cfg, $payload);

        $model = new ProductAttribute();
        $model->setAttribute('product_id', $parent->id);
        $this->loadAttributes($model, $input);
        $this->validateModel($model, $input, false);
        if (!$model->save(false)) {
            throw new ValidationException($model->getErrors());
        }

        $this->syncValues($model, $payload['options'] ?? []);

        Yii::$app->response->setStatusCode(201);
        return [
            'data' => $this->serialize($model, $cfg, true),
            'message' => 'Product attribute created successfully.',
        ];
    }

    public function actionUpdate($id)
    {
        $cfg = $this->cfg();
        [$parent] = $this->resolveParentIfNested($cfg);
        $this->authorize('update', $cfg);
        $this->resolveExpand($cfg);
        $model = $this->findModel($cfg, $id);

        [$input, $payload] = $this->parseBody($cfg);
        $this->validatePayload($cfg, $payload);

        $this->loadAttributes($model, $input);
        $this->validateModel($model, $input, true);
        if (!$model->save(false)) {
            throw new ValidationException($model->getErrors());
        }

        if (array_key_exists('options', $payload)) {
            $this->syncValues($model, $payload['options'] ?? []);
        }

        return [
            'data' => $this->serialize($model, $cfg, true),
            'message' => 'Product attribute updated successfully.',
        ];
    }

    private function syncValues(ProductAttribute $model, array $options): void
    {
        Yii::$app->db->createCommand()
            ->delete('products_product_attribute_values', ['product_attribute_id' => $model->id])
            ->execute();
        foreach ($options as $optionId) {
            $value = new ProductAttributeValue();
            $value->setAttributes([
                'product_id' => $model->product_id,
                'attribute_id' => $model->attribute_id,
                'product_attribute_id' => $model->id,
                'attribute_option_id' => (int) $optionId,
            ], false);
            $value->save(false);
        }
    }
}
