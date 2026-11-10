<?php

namespace app\controllers\v1;

use app\components\ResourceController;
use app\components\ValidationException;

class QuantityController extends ResourceController
{
    protected function verbs(): array
    {
        return array_merge(parent::verbs(), [
            'apply' => ['POST'],
            'clear' => ['POST'],
        ]);
    }

    public function actionApply($id)
    {
        $cfg = $this->cfg();
        $this->authorize('create', $cfg);
        $record = $this->findModel($cfg, $id);

        if (!$record->inventory_quantity_set) {
            throw new ValidationException([], 'Quantity is not marked for inventory adjustment.');
        }

        $record->quantity = $record->counted_quantity ?? $record->quantity;
        $record->counted_quantity = null;
        $record->difference_quantity = 0;
        $record->inventory_diff_quantity = 0;
        $record->inventory_quantity_set = false;
        $record->save(false);

        return [
            'data' => $this->serialize($record, $cfg, true),
            'message' => 'Quantity applied successfully.',
        ];
    }

    public function actionClear($id)
    {
        $cfg = $this->cfg();
        $this->authorize('create', $cfg);
        $record = $this->findModel($cfg, $id);

        $record->counted_quantity = null;
        $record->difference_quantity = 0;
        $record->inventory_diff_quantity = 0;
        $record->inventory_quantity_set = false;
        $record->save(false);

        return [
            'data' => $this->serialize($record, $cfg, true),
            'message' => 'Quantity cleared successfully.',
        ];
    }
}
