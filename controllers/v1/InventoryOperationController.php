<?php

namespace app\controllers\v1;

use Yii;
use yii\db\Query;
use app\components\ResourceController;
use app\components\TransferWorkflow;
use app\components\ValidationException;

/**
 * Workflow endpoint untuk receipts/deliveries/internal-transfers/dropships.
 *
 * Sumber memakai OperationController + InventoryManager (TransferWorkflow) untuk
 * check-availability, todo, validate, cancel, dan return. Kelas ini memakai service port
 * `app\components\TransferWorkflow`.
 */
class InventoryOperationController extends ResourceController
{
    private const TYPES = [
        'receipt' => 'incoming',
        'delivery' => 'outgoing',
        'internal-transfer' => 'internal',
        'dropship' => 'dropship',
    ];

    protected function verbs(): array
    {
        return array_merge(parent::verbs(), [
            'check-availability' => ['POST'],
            'todo' => ['POST'],
            'validate' => ['POST'],
            'cancel' => ['POST'],
            'return' => ['POST'],
        ]);
    }

    public function actionCheckAvailability($id)
    {
        $operation = $this->operation($id);

        if (!in_array($operation['state'], ['waiting', 'confirmed', 'assigned'], true)) {
            throw new ValidationException([], 'Only waiting, confirmed or assigned operations can check availability.');
        }

        $eligible = (new Query())->from('inventories_moves')
            ->where(['operation_id' => (int) $operation['id']])
            ->andWhere(['in', 'state', ['waiting', 'confirmed', 'partially_assigned']])
            ->exists();
        if (!$eligible) {
            throw new ValidationException([], 'No operation moves are eligible for availability check.');
        }

        $operation = $this->workflow()->reserve($operation);

        return $this->respond($operation, 'availability checked successfully.');
    }

    public function actionTodo($id)
    {
        $operation = $this->operation($id);

        if ($operation['state'] !== 'draft') {
            throw new ValidationException([], 'Only draft operations can be set to todo.');
        }
        if (!(new Query())->from('inventories_moves')->where(['operation_id' => (int) $operation['id']])->exists()) {
            throw new ValidationException([], 'Cannot set operation to todo without moves.');
        }

        $operation = $this->workflow()->confirm($operation);

        return $this->respond($operation, 'set to todo successfully.');
    }

    public function actionValidate($id)
    {
        $operation = $this->operation($id);

        if (in_array($operation['state'], ['done', 'canceled'], true)) {
            throw new ValidationException([], 'Only non-done and non-canceled operations can be validated.');
        }

        $operation = $this->workflow()->complete($operation);

        return $this->respond($operation, 'validated successfully.');
    }

    public function actionCancel($id)
    {
        $operation = $this->operation($id);

        if (in_array($operation['state'], ['done', 'canceled'], true)) {
            throw new ValidationException([], 'Only non-done and non-canceled operations can be canceled.');
        }

        $operation = $this->workflow()->cancel($operation);

        return $this->respond($operation, 'canceled successfully.');
    }

    public function actionReturn($id)
    {
        $operation = $this->operation($id);

        if ($operation['state'] !== 'done') {
            throw new ValidationException([], 'Only done operations can be returned.');
        }

        $operation = $this->workflow()->createReturn($operation);

        return $this->respond($operation, 'return created successfully.');
    }

    private function workflow(): TransferWorkflow
    {
        return new TransferWorkflow((int) (Yii::$app->access?->currentUserId() ?? 0));
    }

    private function operation($id): array
    {
        $cfg = $this->cfg();
        $this->authorize('update', $cfg);

        $operation = (new Query())->from('inventories_operations')->where(['id' => (int) $id])->one();
        if (!$operation) {
            throw new \yii\web\NotFoundHttpException('Resource not found.');
        }

        $type = (new Query())->from('inventories_operation_types')
            ->select('type')->where(['id' => (int) $operation['operation_type_id']])->scalar();
        if ($type !== self::TYPES[$this->resourceKey()]) {
            throw new \yii\web\NotFoundHttpException('Resource not found.');
        }

        return $operation;
    }

    private function resourceKey(): string
    {
        return (string) Yii::$app->request->get('resource');
    }

    private function respond(array $operation, string $suffix): array
    {
        $label = $this->cfg()['label'] ?? 'Operation';

        return [
            'data' => $operation,
            'message' => $label . ' ' . $suffix,
        ];
    }
}
