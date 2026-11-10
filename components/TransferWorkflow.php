<?php

namespace app\components;

use Yii;
use yii\db\Query;

/**
 * Port inti dari Webkul\Inventory\Services\TransferWorkflow
 * (confirm / reserve / complete / cancel / createReturn).
 *
 * Bedanya dengan sumber:
 * - MoveConfirmer/MoveReserver/MoveCompleter/MoveCanceller digabung ke kelas ini.
 * - Penulisan stok memakai pola yang sama dengan PosOrderService: `inventories_moves` +
 *   `inventories_move_lines` + `inventories_product_quantities` (tanpa picking/route/putaway).
 * - Cross-link chatter dan event (OperationDone/Canceled/...) tidak dijalankan (modul chatter
 *   belum diport).
 */
class TransferWorkflow
{
    public function __construct(private int $userId)
    {
    }

    public function confirm(array $operation): array
    {
        $db = Yii::$app->db;
        foreach ($this->moves((int) $operation['id']) as $move) {
            if ($move['state'] === 'draft') {
                $db->createCommand()->update('inventories_moves', [
                    'state' => 'confirmed',
                    'scheduled_at' => $move['scheduled_at'] ?? date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ], ['id' => (int) $move['id']])->execute();
            }
        }

        return $this->refreshState($operation);
    }

    public function reserve(array $operation): array
    {
        if ($operation['state'] === 'draft') {
            $operation = $this->confirm($operation);
        }

        $moves = array_values(array_filter($this->moves((int) $operation['id']), fn ($move) => !in_array($move['state'], ['draft', 'canceled', 'done'], true)));
        if ($moves === []) {
            throw new ValidationException([], 'No operation moves are eligible for availability check.');
        }

        $db = Yii::$app->db;
        foreach ($moves as $move) {
            $wanted = abs((float) ($move['product_uom_qty'] ?? 0));
            $available = $this->availableQuantity((int) $move['product_id'], (int) $move['source_location_id']);
            $reserve = min($wanted, max($available, 0.0));

            if ($reserve > 0) {
                $this->adjustReserved((int) $move['product_id'], (int) $move['source_location_id'], $reserve, (int) $move['company_id']);
            }

            $state = match (true) {
                $wanted <= 0 => 'confirmed',
                $reserve >= $wanted => 'assigned',
                $reserve > 0 => 'partially_assigned',
                default => 'confirmed',
            };

            $db->createCommand()->update('inventories_moves', [
                'state' => $state,
                'reservation_date' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ], ['id' => (int) $move['id']])->execute();
        }

        return $this->refreshState($operation);
    }

    public function complete(array $operation): array
    {
        $db = Yii::$app->db;
        $pending = array_values(array_filter($this->moves((int) $operation['id']), fn ($move) => !in_array($move['state'], ['done', 'canceled'], true)));
        if ($pending === []) {
            throw new ValidationException([], 'Only non-done and non-canceled operations can be validated.');
        }

        $this->markEverythingPickedIfNeeded($operation);

        foreach ($pending as $move) {
            $move = $this->moves((int) $operation['id'], (int) $move['id'])[0] ?? $move;
            $qty = abs((float) $move['quantity']);
            if ($qty == 0.0) {
                $qty = abs((float) ($move['product_uom_qty'] ?: $move['product_qty']));
            }
            $source = $move['source_location_id'] !== null ? (int) $move['source_location_id'] : null;
            $destination = $move['destination_location_id'] !== null ? (int) $move['destination_location_id'] : null;

            if ($source !== null) {
                $this->releaseReserved((int) $move['product_id'], $source, abs((float) $move['product_uom_qty']));
                $this->adjustQuantity((int) $move['product_id'], $source, -$qty, (int) $move['company_id']);
            }
            if ($destination !== null) {
                $this->adjustQuantity((int) $move['product_id'], $destination, $qty, (int) $move['company_id']);
            }

            $db->createCommand()->insert('inventories_move_lines', [
                'move_id' => (int) $move['id'],
                'operation_id' => (int) $operation['id'],
                'product_id' => (int) $move['product_id'],
                'uom_id' => $move['uom_id'] !== null ? (int) $move['uom_id'] : null,
                'qty' => $qty,
                'uom_qty' => $qty,
                'state' => 'done',
                'is_picked' => 1,
                'source_location_id' => $source,
                'destination_location_id' => $destination,
                'partner_id' => $move['partner_id'] !== null ? (int) $move['partner_id'] : null,
                'company_id' => $move['company_id'] !== null ? (int) $move['company_id'] : null,
                'creator_id' => $this->userId,
                'scheduled_at' => date('Y-m-d H:i:s'),
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ])->execute();

            $db->createCommand()->update('inventories_moves', [
                'state' => 'done',
                'quantity' => $qty,
                'product_qty' => $qty,
                'is_picked' => 1,
                'updated_at' => date('Y-m-d H:i:s'),
            ], ['id' => (int) $move['id']])->execute();
        }

        $this->purgeEmptyQuantities();

        $operation = $this->refreshState($operation);
        $db->createCommand()->update('inventories_operations', [
            'closed_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ], ['id' => (int) $operation['id']])->execute();

        return $this->reload((int) $operation['id']);
    }

    public function cancel(array $operation): array
    {
        $db = Yii::$app->db;
        foreach ($this->moves((int) $operation['id']) as $move) {
            if (in_array($move['state'], ['done'], true)) {
                continue;
            }
            if ($move['source_location_id'] !== null && in_array($move['state'], ['assigned', 'partially_assigned'], true)) {
                $this->releaseReserved(
                    (int) $move['product_id'],
                    (int) $move['source_location_id'],
                    abs((float) $move['product_uom_qty'])
                );
            }
            $db->createCommand()->update('inventories_moves', [
                'state' => 'canceled',
                'updated_at' => date('Y-m-d H:i:s'),
            ], ['id' => (int) $move['id']])->execute();
        }

        $this->purgeEmptyQuantities();

        return $this->refreshState($operation);
    }

    public function createReturn(array $operation): array
    {
        $db = Yii::$app->db;
        $moves = $this->moves((int) $operation['id']);
        if ($moves === []) {
            throw new ValidationException([], 'This operation has no moves to return.');
        }

        $operationType = (new Query())->from('inventories_operation_types')
            ->where(['id' => (int) $operation['operation_type_id']])->one();
        $returnTypeId = $operationType['return_operation_type_id'] ?? null;

        $name = SequenceService::next('inventory.operation', $operation['company_id'] !== null ? (int) $operation['company_id'] : null, [
            'name' => 'Inventory Operation',
            'prefix' => 'RET/',
            'padding' => 5,
        ]);

        $db->createCommand()->insert('inventories_operations', [
            'name' => $name,
            'origin' => 'Return of ' . $operation['name'],
            'move_type' => $operation['move_type'],
            'state' => 'draft',
            'is_favorite' => 0,
            'has_deadline_issue' => 0,
            'is_printed' => 0,
            'is_locked' => 0,
            'scheduled_at' => date('Y-m-d H:i:s'),
            'user_id' => null,
            'owner_id' => $this->userId,
            'operation_type_id' => $returnTypeId !== null ? (int) $returnTypeId : (int) $operation['operation_type_id'],
            'source_location_id' => $operation['destination_location_id'],
            'destination_location_id' => $operation['source_location_id'],
            'return_id' => (int) $operation['id'],
            'partner_id' => $operation['partner_id'] !== null ? (int) $operation['partner_id'] : null,
            'company_id' => $operation['company_id'] !== null ? (int) $operation['company_id'] : null,
            'creator_id' => $this->userId,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ])->execute();
        $returnId = (int) $db->getLastInsertID();

        foreach ($moves as $move) {
            if (in_array($move['state'], ['canceled'], true)) {
                continue;
            }
            $qty = abs((float) $move['quantity']);
            if ($qty == 0.0) {
                $qty = abs((float) $move['product_uom_qty']);
            }
            if ($qty == 0.0) {
                continue;
            }

            $db->createCommand()->insert('inventories_moves', [
                'name' => $name,
                'state' => 'draft',
                'origin' => $name,
                'product_id' => (int) $move['product_id'],
                'uom_id' => $move['uom_id'] !== null ? (int) $move['uom_id'] : null,
                'product_qty' => $qty,
                'product_uom_qty' => $qty,
                'quantity' => 0,
                'is_picked' => 0,
                'is_scraped' => 0,
                'is_inventory' => 0,
                'is_refund' => 1,
                'source_location_id' => $operation['destination_location_id'],
                'destination_location_id' => $operation['source_location_id'],
                'warehouse_id' => $move['warehouse_id'] !== null ? (int) $move['warehouse_id'] : null,
                'operation_type_id' => $returnTypeId !== null ? (int) $returnTypeId : (int) $operation['operation_type_id'],
                'operation_id' => $returnId,
                'origin_returned_move_id' => (int) $move['id'],
                'partner_id' => $operation['partner_id'] !== null ? (int) $operation['partner_id'] : null,
                'company_id' => $operation['company_id'] !== null ? (int) $operation['company_id'] : null,
                'creator_id' => $this->userId,
                'scheduled_at' => date('Y-m-d H:i:s'),
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ])->execute();
        }

        return $this->reload($returnId);
    }

    public function computeState(array $operation): string
    {
        $moves = $this->moves((int) $operation['id']);
        if ($moves === []) {
            return $operation['state'] ?? 'draft';
        }

        $states = array_map(fn ($move) => (string) $move['state'], $moves);

        return match (true) {
            !array_diff($states, ['canceled']) => 'canceled',
            !array_diff($states, ['done']) => 'done',
            in_array('assigned', $states, true) => 'assigned',
            in_array('partially_assigned', $states, true) => 'assigned',
            in_array('confirmed', $states, true) => 'confirmed',
            default => 'draft',
        };
    }

    private function refreshState(array $operation): array
    {
        $state = $this->computeState($operation);
        Yii::$app->db->createCommand()->update('inventories_operations', [
            'state' => $state,
            'updated_at' => date('Y-m-d H:i:s'),
        ], ['id' => (int) $operation['id']])->execute();

        return $this->reload((int) $operation['id']);
    }

    private function markEverythingPickedIfNeeded(array $operation): void
    {
        $hasQuantity = false;
        $hasPick = false;
        foreach ($this->moves((int) $operation['id']) as $move) {
            if ((float) $move['quantity'] > 0) {
                $hasQuantity = true;
            }
            if (!(int) $move['is_scraped'] && (int) $move['is_picked']) {
                $hasPick = true;
            }
            if ($hasQuantity && $hasPick) {
                return;
            }
        }

        if ($hasQuantity && !$hasPick) {
            Yii::$app->db->createCommand()->update(
                'inventories_moves',
                ['is_picked' => 1, 'updated_at' => date('Y-m-d H:i:s')],
                ['operation_id' => (int) $operation['id']]
            )->execute();
        }
    }

    private function moves(int $operationId, ?int $moveId = null): array
    {
        $query = (new Query())->from('inventories_moves')->where(['operation_id' => $operationId]);
        if ($moveId !== null) {
            $query->andWhere(['id' => $moveId]);
        }

        return $query->orderBy(['id' => SORT_ASC])->all();
    }

    private function reload(int $operationId): array
    {
        $row = (new Query())->from('inventories_operations')->where(['id' => $operationId])->one();

        return $row === false ? [] : $row;
    }

    private function quantityRow(int $productId, ?int $locationId): ?array
    {
        if ($locationId === null) {
            return null;
        }

        $row = (new Query())->from('inventories_product_quantities')
            ->where(['product_id' => $productId, 'location_id' => $locationId])->one();

        return $row === false ? null : $row;
    }

    private function availableQuantity(int $productId, ?int $locationId): float
    {
        $row = $this->quantityRow($productId, $locationId);
        if ($row === null) {
            return 0.0;
        }

        return round((float) $row['quantity'] - (float) $row['reserved_quantity'], 4);
    }

    private function adjustReserved(int $productId, int $locationId, float $delta, int $companyId): void
    {
        $db = Yii::$app->db;
        $row = $this->quantityRow($productId, $locationId);

        if ($row === null) {
            $db->createCommand()->insert('inventories_product_quantities', [
                'product_id' => $productId,
                'location_id' => $locationId,
                'company_id' => $companyId ?: null,
                'quantity' => 0,
                'reserved_quantity' => $delta,
                'incoming_at' => date('Y-m-d H:i:s'),
                'creator_id' => $this->userId,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ])->execute();
            return;
        }

        $db->createCommand()->update('inventories_product_quantities', [
            'reserved_quantity' => round((float) $row['reserved_quantity'] + $delta, 4),
            'updated_at' => date('Y-m-d H:i:s'),
        ], ['id' => (int) $row['id']])->execute();
    }

    private function releaseReserved(int $productId, int $locationId, float $quantity): void
    {
        $db = Yii::$app->db;
        $row = $this->quantityRow($productId, $locationId);
        if ($row === null) {
            return;
        }

        $db->createCommand()->update('inventories_product_quantities', [
            'reserved_quantity' => max(0.0, round((float) $row['reserved_quantity'] - $quantity, 4)),
            'updated_at' => date('Y-m-d H:i:s'),
        ], ['id' => (int) $row['id']])->execute();
    }

    private function adjustQuantity(int $productId, int $locationId, float $delta, int $companyId): void
    {
        $db = Yii::$app->db;
        $row = $this->quantityRow($productId, $locationId);

        if ($row === null) {
            $db->createCommand()->insert('inventories_product_quantities', [
                'product_id' => $productId,
                'location_id' => $locationId,
                'company_id' => $companyId ?: null,
                'quantity' => $delta,
                'reserved_quantity' => 0,
                'incoming_at' => date('Y-m-d H:i:s'),
                'creator_id' => $this->userId,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ])->execute();
            return;
        }

        $db->createCommand()->update('inventories_product_quantities', [
            'quantity' => round((float) $row['quantity'] + $delta, 4),
            'updated_at' => date('Y-m-d H:i:s'),
        ], ['id' => (int) $row['id']])->execute();
    }

    private function purgeEmptyQuantities(): void
    {
        Yii::$app->db->createCommand()->delete('inventories_product_quantities', [
            'and',
            ['quantity' => 0],
            ['reserved_quantity' => 0],
            ['counted_quantity' => 0],
        ])->execute();
    }
}
