<?php

namespace app\controllers\v1;

use Yii;
use yii\db\Query;
use yii\web\BadRequestHttpException;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;
use app\components\ApiController;
use app\components\PosOrderService;
use app\components\ValidationException;
use app\models\PosCashMovement;
use app\models\PosCategory;
use app\models\PosConfig;
use app\models\PosOrder;
use app\models\PosOrderLine;
use app\models\PosPayment;
use app\models\PosSession;

class PointOfSaleController extends ApiController
{
    protected function verbs(): array
    {
        return [
            'registers' => ['GET'],
            'discard' => ['POST'],
            'catalog' => ['GET'],
            'current-session' => ['GET'],
            'open-session' => ['POST'],
            'opening-control' => ['POST'],
            'closing-control' => ['POST'],
            'close-session' => ['POST'],
            'cash-movement' => ['POST'],
            'orders' => ['GET'],
            'sales-details' => ['GET'],
            'closing-report' => ['GET'],
            'sync-orders' => ['POST'],
            'refundable-lines' => ['GET'],
            'refund' => ['POST'],
            'invoice' => ['POST'],
            'invoice-pdf' => ['GET'],
            'receipt' => ['GET'],
            'boot' => ['GET'],
            'cancel-draft-orders' => ['POST'],
        ];
    }

    private function requirePermission(string ...$permissions): void
    {
        foreach ($permissions as $permission) {
            if (Yii::$app->access->can($permission)) {
                return;
            }
        }
        throw new ForbiddenHttpException('This action is unauthorized.');
    }

    private function body(): array
    {
        $body = Yii::$app->request->getBodyParams();
        return is_array($body) ? $body : [];
    }

    private function findConfig($id): PosConfig
    {
        $config = PosConfig::find()->where(['id' => (int) $id, 'deleted_at' => null])->one();
        if ($config === null) {
            throw new NotFoundHttpException('Register not found.');
        }
        return $config;
    }

    private function findSession($id): PosSession
    {
        $session = PosSession::findOne(['id' => (int) $id]);
        if ($session === null) {
            throw new NotFoundHttpException('Session not found.');
        }
        return $session;
    }

    private function findOrder($id): PosOrder
    {
        $query = PosOrder::find();
        if (is_numeric($id)) {
            $query->where(['id' => (int) $id]);
        } else {
            $query->where(['uuid' => (string) $id]);
        }
        $order = $query->one();
        if ($order === null) {
            throw new NotFoundHttpException('Order not found.');
        }
        return $order;
    }

    private function orderService(): PosOrderService
    {
        return new PosOrderService((int) (Yii::$app->access->currentUserId() ?? 0));
    }

    private function liveSessionFor(PosConfig $config): ?PosSession
    {
        return PosSession::find()
            ->where(['config_id' => $config->id])
            ->andWhere(['not in', 'state', [PosSession::STATE_CLOSED]])
            ->orderBy(['id' => SORT_DESC])
            ->one();
    }

    private function sessionData(PosSession $session): array
    {
        return [
            'id' => (int) $session->id,
            'name' => $session->name,
            'state' => $session->state,
            'config_id' => $session->config_id !== null ? (int) $session->config_id : null,
            'stock_update_mode' => $session->stock_update_mode,
            'started_at' => self::iso($session->started_at),
            'stopped_at' => self::iso($session->stopped_at),
            'cash_balance_start' => self::float($session->cash_balance_start),
            'cash_balance_end' => self::float($session->cash_balance_end),
            'cash_difference' => self::float($session->cash_difference),
            'cash_transaction_total' => self::float($session->cash_transaction_total),
            'order_count' => (int) $session->order_count,
            'total_payments_amount' => self::float($session->total_payments_amount),
            'has_failed_operations' => (bool) $session->has_failed_operations,
            'is_rescue' => (bool) $session->is_rescue,
            'user_id' => $session->user_id !== null ? (int) $session->user_id : null,
            'currency_id' => $session->currency_id !== null ? (int) $session->currency_id : null,
            'company_id' => $session->company_id !== null ? (int) $session->company_id : null,
        ];
    }

    public function actionOrderHistory()
    {
        $this->requirePermission('view_any_point_of_sale_order', 'view_any_point_of_sale_session');

        $page = max(1, (int) Yii::$app->request->get('page', 1) ?: 1);
        $perPage = min(100, max(1, (int) Yii::$app->request->get('per_page', 20) ?: 20));
        $q = trim((string) Yii::$app->request->get('q', ''));
        $state = trim((string) Yii::$app->request->get('state', ''));
        $sessionId = (int) Yii::$app->request->get('session_id', 0);
        $from = trim((string) Yii::$app->request->get('from', ''));
        $to = trim((string) Yii::$app->request->get('to', ''));

        $query = (new Query())->from(['o' => 'pos_orders']);
        if ($state !== '') {
            $query->andWhere(['o.state' => $state]);
        }
        if ($sessionId > 0) {
            $query->andWhere(['o.session_id' => $sessionId]);
        }
        if ($q !== '') {
            $query->andWhere(['or', ['like', 'o.name', $q], ['like', 'o.reference', $q]]);
        }
        if ($from !== '') {
            $query->andWhere(['>=', 'o.ordered_at', $from . ' 00:00:00']);
        }
        if ($to !== '') {
            $query->andWhere(['<=', 'o.ordered_at', $to . ' 23:59:59']);
        }

        $total = (int) (clone $query)->count();
        $rows = (clone $query)
            ->leftJoin('partners_partners p', 'p.id = o.partner_id')
            ->leftJoin('pos_configs c', 'c.id = o.config_id')
            ->leftJoin('users u', 'u.id = o.user_id')
            ->select([
                'o.id', 'o.name', 'o.reference', 'o.state', 'o.ordered_at', 'o.confirmed_at',
                'o.amount_untaxed', 'o.amount_tax', 'o.amount_total', 'o.amount_paid',
                'o.amount_return', 'o.session_id', 'o.config_id', 'o.currency_id',
                'partner_name' => 'p.name', 'register_name' => 'c.name', 'cashier_name' => 'u.name',
            ])
            ->orderBy(['o.id' => SORT_DESC])
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->all();

        $iso = static fn ($value) => $value !== null ? gmdate('c', strtotime((string) $value)) : null;

        $data = array_map(fn ($row) => [
            'id' => (int) $row['id'],
            'name' => $row['name'],
            'reference' => $row['reference'],
            'state' => $row['state'],
            'ordered_at' => $iso($row['ordered_at']),
            'confirmed_at' => $iso($row['confirmed_at']),
            'amount_untaxed' => (float) $row['amount_untaxed'],
            'amount_tax' => (float) $row['amount_tax'],
            'amount_total' => (float) $row['amount_total'],
            'amount_paid' => (float) $row['amount_paid'],
            'amount_return' => (float) $row['amount_return'],
            'session_id' => $row['session_id'] !== null ? (int) $row['session_id'] : null,
            'config_id' => $row['config_id'] !== null ? (int) $row['config_id'] : null,
            'partner_name' => $row['partner_name'],
            'register_name' => $row['register_name'],
            'cashier_name' => $row['cashier_name'],
        ], $rows);

        $lastPage = max(1, (int) ceil($total / $perPage));

        return [
            'data' => $data,
            'meta' => [
                'current_page' => $page,
                'from' => $total === 0 ? null : ($page - 1) * $perPage + 1,
                'last_page' => $lastPage,
                'per_page' => $perPage,
                'to' => $total === 0 ? null : min($total, $page * $perPage),
                'total' => $total,
            ],
        ];
    }

    /* ---------------- Kategori POS (CRUD) ---------------- */

    public function actionCategories()
    {
        $this->requirePermission('view_any_point_of_sale_category', 'view_any_point_of_sale_config');

        $rows = (new Query())
            ->from(['c' => 'pos_categories'])
            ->where(['c.deleted_at' => null])
            ->orderBy(['c.sort' => SORT_ASC, 'c.id' => SORT_ASC])
            ->select(['c.id', 'c.name', 'c.color', 'c.sort', 'c.parent_id', 'c.image'])
            ->all();

        return ['data' => array_map(fn ($row) => $this->categoryData($row), $rows)];
    }

    public function actionCategory($id)
    {
        $this->requirePermission('view_point_of_sale_category', 'view_any_point_of_sale_config');

        return ['data' => $this->categoryData($this->findCategory($id))];
    }

    public function actionCreateCategory()
    {
        $this->requirePermission('create_point_of_sale_category');

        $body = $this->categoryPayload();
        $model = new PosCategory();
        $model->setAttributes(array_merge($body, [
            'creator_id' => Yii::$app->access->currentUserId(),
            'sort' => $body['sort'] ?? 0,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]), false);
        $model->save(false);

        $this->syncCategoryPivots((int) $model->id, $body);

        Yii::$app->response->setStatusCode(201);

        return ['data' => $this->categoryData($this->findCategory($model->id)), 'message' => 'Category created successfully.'];
    }

    public function actionUpdateCategory($id)
    {
        $this->requirePermission('update_point_of_sale_category');

        $category = $this->findCategory($id);
        $body = $this->categoryPayload();

        $model = PosCategory::findOne(['id' => (int) $category['id']]);
        if ($model === null) {
            throw new NotFoundHttpException('Resource not found.');
        }
        $model->setAttributes(array_intersect_key($body, array_flip(['name', 'color', 'sort', 'parent_id', 'image'])), false);
        $model->updated_at = date('Y-m-d H:i:s');
        $model->save(false);

        $this->syncCategoryPivots((int) $category['id'], $body);

        return ['data' => $this->categoryData($this->findCategory($id)), 'message' => 'Category updated successfully.'];
    }

    public function actionDeleteCategory($id)
    {
        $this->requirePermission('delete_point_of_sale_category', 'delete_any_point_of_sale_category');

        $category = $this->findCategory($id);
        Yii::$app->db->createCommand()->update('pos_categories', [
            'deleted_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ], ['id' => (int) $category['id']])->execute();

        return ['message' => 'Category deleted successfully.'];
    }

    private function categoryPayload(): array
    {
        $body = Yii::$app->request->getBodyParams();
        if (!is_array($body)) {
            $body = [];
        }

        $payload = [];
        if (array_key_exists('name', $body)) {
            $name = trim((string) $body['name']);
            if ($name === '') {
                throw new ValidationException(['name' => ['The name field is required.']]);
            }
            $payload['name'] = $name;
        }
        if (array_key_exists('color', $body)) {
            $payload['color'] = $body['color'] !== '' ? (string) $body['color'] : null;
        }
        if (array_key_exists('sort', $body)) {
            $payload['sort'] = (int) $body['sort'];
        }
        if (array_key_exists('parent_id', $body)) {
            $payload['parent_id'] = $body['parent_id'] !== null && $body['parent_id'] !== '' ? (int) $body['parent_id'] : null;
        }
        if (array_key_exists('image', $body)) {
            $payload['image'] = $body['image'] !== '' ? (string) $body['image'] : null;
        }
        if (array_key_exists('product_ids', $body)) {
            $payload['product_ids'] = array_values(array_unique(array_map('intval', (array) $body['product_ids'])));
        }
        if (array_key_exists('config_ids', $body)) {
            $payload['config_ids'] = array_values(array_unique(array_map('intval', (array) $body['config_ids'])));
        }

        return $payload;
    }

    private function syncCategoryPivots(int $categoryId, array $payload): void
    {
        $db = Yii::$app->db;

        if (array_key_exists('product_ids', $payload)) {
            $db->createCommand()->delete('pos_category_products', ['category_id' => $categoryId])->execute();
            foreach ($payload['product_ids'] as $productId) {
                if ((new Query())->from('products_products')->where(['id' => $productId])->exists()) {
                    $db->createCommand()->insert('pos_category_products', [
                        'category_id' => $categoryId,
                        'product_id' => $productId,
                    ])->execute();
                }
            }
        }

        if (array_key_exists('config_ids', $payload)) {
            $db->createCommand()->delete('pos_config_categories', ['category_id' => $categoryId])->execute();
            foreach ($payload['config_ids'] as $configId) {
                if ((new Query())->from('pos_configs')->where(['id' => $configId])->exists()) {
                    $db->createCommand()->insert('pos_config_categories', [
                        'config_id' => $configId,
                        'category_id' => $categoryId,
                    ])->execute();
                }
            }
        }
    }

    private function findCategory($id): array
    {
        $row = (new Query())
            ->from(['c' => 'pos_categories'])
            ->where(['c.id' => (int) $id, 'c.deleted_at' => null])
            ->one();

        if (!$row) {
            throw new NotFoundHttpException('Resource not found.');
        }

        return $row;
    }

    private function categoryData(array $row): array
    {
        $categoryId = (int) $row['id'];

        $productIds = (new Query())->from('pos_category_products')
            ->select('product_id')->where(['category_id' => $categoryId])->column();
        $productIds = array_map('intval', $productIds);
        $configIds = (new Query())->from('pos_config_categories')
            ->select('config_id')->where(['category_id' => $categoryId])->column();
        $configIds = array_map('intval', $configIds);

        $parentName = null;
        if ($row['parent_id'] !== null) {
            $parentName = (new Query())->from('pos_categories')->select('name')->where(['id' => (int) $row['parent_id']])->scalar();
        }

        return [
            'id' => $categoryId,
            'name' => $row['name'],
            'color' => $row['color'],
            'sort' => $row['sort'] !== null ? (int) $row['sort'] : 0,
            'parent_id' => $row['parent_id'] !== null ? (int) $row['parent_id'] : null,
            'parent_name' => $parentName !== false ? $parentName : null,
            'image' => $row['image'],
            'product_ids' => $productIds,
            'product_count' => count($productIds),
            'config_ids' => $configIds,
            'config_count' => count($configIds),
        ];
    }

    private function registerData(PosConfig $config, ?PosSession $live): array
    {
        return [
            'id' => (int) $config->id,
            'name' => $config->name,
            'code' => $config->code,
            'is_active' => (bool) $config->is_active,
            'is_restaurant' => (bool) $config->is_restaurant,
            'currency_id' => $config->currency_id !== null ? (int) $config->currency_id : null,
            'company_id' => $config->company_id !== null ? (int) $config->company_id : null,
            'live_session' => $live === null ? null : [
                'id' => (int) $live->id,
                'name' => $live->name,
                'state' => $live->state,
                'user' => $live->user?->name,
                'is_discardable' => $live->order_count === 0,
            ],
        ];
    }

    private function orderData(PosOrder $order): array
    {
        $lines = [];
        foreach ($order->lines as $line) {
            $taxIds = [];
            foreach ($line->taxes as $tax) {
                $taxIds[] = (int) $tax->id;
            }
            $lines[] = [
                'id' => (int) $line->id,
                'uuid' => $line->uuid,
                'product_id' => $line->product_id !== null ? (int) $line->product_id : null,
                'uom_id' => $line->uom_id !== null ? (int) $line->uom_id : null,
                'full_product_name' => $line->full_product_name ?? $line->product?->name,
                'qty' => self::float($line->qty),
                'price_unit' => self::float($line->price_unit),
                'discount' => self::float($line->discount),
                'price_subtotal' => self::float($line->price_subtotal),
                'price_subtotal_incl' => self::float($line->price_subtotal_incl),
                'tax_ids' => $taxIds,
            ];
        }

        $payments = [];
        foreach ($order->payments as $payment) {
            $payments[] = [
                'id' => (int) $payment->id,
                'uuid' => $payment->uuid,
                'payment_method_id' => $payment->payment_method_id !== null ? (int) $payment->payment_method_id : null,
                'payment_method' => $payment->paymentMethod?->name,
                'amount' => self::float($payment->amount),
                'is_change' => (bool) $payment->is_change,
            ];
        }

        return [
            'id' => (int) $order->id,
            'uuid' => $order->uuid,
            'name' => $order->name,
            'reference' => $order->reference,
            'receipt_code' => $order->receipt_code,
            'tracking_number' => $order->tracking_number,
            'state' => $order->state,
            'ordered_at' => self::iso($order->ordered_at),
            'amount_untaxed' => self::float($order->amount_untaxed),
            'amount_tax' => self::float($order->amount_tax),
            'amount_total' => self::float($order->amount_total),
            'amount_paid' => self::float($order->amount_paid),
            'amount_return' => self::float($order->amount_return),
            'is_invoiced' => (bool) $order->is_invoiced,
            'is_to_invoice' => (bool) $order->is_to_invoice,
            'has_failed_operation' => (bool) $order->has_failed_operation,
            'account_move_id' => $order->account_move_id !== null ? (int) $order->account_move_id : null,
            'operation_id' => $order->operation_id !== null ? (int) $order->operation_id : null,
            'amount_difference' => self::float($order->amount_difference),
            'note' => $order->note,
            'cashier' => $order->user?->name,
            'partner' => $order->partner === null ? null : [
                'id' => (int) $order->partner->id,
                'name' => $order->partner->name,
            ],
            'currency' => $order->currency === null ? null : [
                'id' => (int) $order->currency->id,
                'name' => $order->currency->name,
            ],
            'lines' => $lines,
            'payments' => $payments,
        ];
    }

    public function actionRegisters()
    {
        $this->requirePermission('view_any_point_of_sale_config');

        $query = PosConfig::find()
            ->where(['is_active' => 1, 'deleted_at' => null])
            ->orderBy(['sort' => SORT_ASC, 'id' => SORT_ASC]);

        if (!Yii::$app->access->bypassScopes()) {
            $companyIds = Yii::$app->access->allowedCompanyIds();
            $query->andWhere($companyIds === []
                ? ['id' => -1]
                : ['or', ['company_id' => null], ['company_id' => $companyIds]]);
        }

        $data = [];
        foreach ($query->all() as $config) {
            $data[] = $this->registerData($config, $this->liveSessionFor($config));
        }

        $own = $this->ownSession();

        return [
            'data' => $data,
            'own_session_id' => $own?->id,
        ];
    }

    private function ownSession(): ?PosSession
    {
        $userId = Yii::$app->access->currentUserId();
        if ($userId === null) {
            return null;
        }
        return PosSession::find()
            ->where(['user_id' => $userId])
            ->andWhere(['not in', 'state', [PosSession::STATE_CLOSED]])
            ->orderBy(['id' => SORT_DESC])
            ->one();
    }

    public function actionDiscard($config)
    {
        $this->requirePermission('view_any_point_of_sale_config', 'create_point_of_sale_session');
        $config = $this->findConfig($config);

        $session = $this->liveSessionFor($config);
        if ($session === null) {
            throw new NotFoundHttpException('Session not found.');
        }
        if ($session->order_count > 0) {
            throw new BadRequestHttpException('Session cannot be discarded.');
        }

        PosSession::deleteAll(['id' => $session->id]);

        return ['data' => null];
    }

    public function actionCatalog($config)
    {
        $this->requirePermission('view_any_point_of_sale_config', 'view_any_point_of_sale_product');
        $config = $this->findConfig($config);

        $search = trim((string) Yii::$app->request->get('search', ''));

        return ['data' => $this->catalogData($config, $search)];
    }

    private function catalogData(PosConfig $config, string $search = ''): array
    {
        $categoryQuery = (new Query())
            ->from(['c' => 'pos_categories'])
            ->where(['c.deleted_at' => null])
            ->orderBy(['c.sort' => SORT_ASC, 'c.id' => SORT_ASC]);

        $configCategoryIds = (new Query())
            ->from('pos_config_categories')
            ->select('category_id')
            ->where(['config_id' => $config->id])
            ->column();
        if ($configCategoryIds !== []) {
            $categoryQuery->andWhere(['c.id' => array_map('intval', $configCategoryIds)]);
        }

        $categories = $categoryQuery->select(['c.id', 'c.name', 'c.color'])->all();

        $warehouse = $config->warehouse_id !== null
            ? (new Query())->from('inventories_warehouses')->where(['id' => (int) $config->warehouse_id])->one()
            : null;
        $stockLocationId = $warehouse !== null && $warehouse['lot_stock_location_id'] !== null
            ? (int) $warehouse['lot_stock_location_id']
            : null;

        $categoriesOut = [];
        foreach ($categories as $category) {
            $productsQuery = (new Query())
                ->from(['p' => 'products_products'])
                ->innerJoin('pos_category_products cp', 'cp.product_id = p.id')
                ->where(['cp.category_id' => (int) $category['id'], 'p.deleted_at' => null])
                ->orderBy(['p.id' => SORT_ASC])
                ->limit(200)
                ->select(['p.id', 'p.name', 'p.price', 'p.barcode', 'p.reference', 'p.uom_id', 'p.is_storable']);
            if ($search !== '') {
                $productsQuery->andWhere(['like', 'p.name', $search]);
            }
            $products = $productsQuery->all();

            // Stok gudang (lokasi stock warehouse milik register) untuk produk di kategori ini.
            $stock = [];
            $productIds = array_map(static fn ($p) => (int) $p['id'], $products);
            if ($stockLocationId !== null && $productIds !== []) {
                $rows = (new Query())
                    ->from('inventories_product_quantities')
                    ->where(['location_id' => $stockLocationId])
                    ->andWhere(['in', 'product_id', $productIds])
                    ->select(['product_id', 'quantity', 'reserved_quantity'])
                    ->all();
                foreach ($rows as $row) {
                    $stock[(int) $row['product_id']] = round((float) $row['quantity'] - (float) $row['reserved_quantity'], 4);
                }
            }

            $categoriesOut[] = [
                'id' => (int) $category['id'],
                'name' => $category['name'],
                'color' => $category['color'],
                'products' => array_map(fn ($p) => [
                    'id' => (int) $p['id'],
                    'name' => $p['name'],
                    'barcode' => $p['barcode'],
                    'reference' => $p['reference'],
                    'price' => (float) $p['price'],
                    'uom_id' => $p['uom_id'] !== null ? (int) $p['uom_id'] : null,
                    'is_storable' => (bool) $p['is_storable'],
                    'stock' => $stock[(int) $p['id']] ?? 0.0,
                ], $products),
            ];
        }

        return [
            'config' => $this->registerData($config, $this->liveSessionFor($config)),
            'warehouse' => $warehouse === null ? null : [
                'id' => (int) $warehouse['id'],
                'name' => $warehouse['name'],
                'stock_location_id' => $stockLocationId,
            ],
            'categories' => $categoriesOut,
            'payment_methods' => array_map(fn ($m) => [
                'id' => (int) $m['id'],
                'name' => $m['name'],
                'is_cash_count' => (bool) $m['is_cash_count'],
            ], (new Query())
                ->from('pos_payment_methods')
                ->where(['deleted_at' => null])
                ->andWhere(['or', ['company_id' => $config->company_id], ['company_id' => null]])
                ->orderBy(['id' => SORT_ASC])
                ->select(['id', 'name', 'is_cash_count'])
                ->all()),
        ];
    }

    public function actionCurrentSession($config)
    {
        $this->requirePermission('view_any_point_of_sale_session', 'view_any_point_of_sale_config');
        $config = $this->findConfig($config);

        $session = $this->liveSessionFor($config);

        return ['data' => $session === null ? null : $this->sessionData($session)];
    }

    public function actionOpenSession($config)
    {
        $this->requirePermission('create_point_of_sale_session');
        $config = $this->findConfig($config);

        if (!$config->is_active) {
            throw new BadRequestHttpException('This register is not active.');
        }
        if ($this->liveSessionFor($config) !== null) {
            throw new BadRequestHttpException('This register already has an open session.');
        }

        $userId = Yii::$app->access->currentUserId();
        $number = (int) PosSession::find()->where(['config_id' => $config->id])->max('sequence_number') + 1;
        $currencyId = $config->currency_id ?? $config->company?->currency_id;

        $session = new PosSession();
        $session->setAttributes([
            'name' => 'POS/' . str_pad((string) $number, 5, '0', STR_PAD_LEFT),
            'state' => PosSession::STATE_OPENED,
            'stock_update_mode' => 'real_time',
            'sequence_number' => $number,
            'login_number' => 1,
            'cash_balance_start' => 0,
            'cash_balance_end' => 0,
            'cash_difference' => 0,
            'cash_transaction_total' => 0,
            'total_payments_amount' => 0,
            'order_count' => 0,
            'operation_count' => 0,
            'is_rescue' => 0,
            'has_failed_operations' => 0,
            'config_id' => $config->id,
            'cash_journal_id' => $config->journal_id,
            'user_id' => $userId,
            'currency_id' => $currencyId,
            'company_id' => $config->company_id,
            'creator_id' => $userId,
            'started_at' => date('Y-m-d H:i:s'),
        ], false);
        $session->save(false);

        Yii::$app->response->setStatusCode(201);

        return [
            'data' => $this->sessionData($session),
            'message' => 'Session opened successfully.',
        ];
    }

    public function actionOpeningControl($session)
    {
        $this->requirePermission('create_point_of_sale_session', 'view_any_point_of_sale_session');
        $session = $this->findSession($session);

        if (in_array($session->state, [PosSession::STATE_CLOSED], true)) {
            throw new BadRequestHttpException('This session is already closed.');
        }

        $body = $this->body();
        $errors = [];
        if (!isset($body['cash_balance_start']) || !is_numeric($body['cash_balance_start']) || (float) $body['cash_balance_start'] < 0) {
            $errors['cash_balance_start'][] = 'The cash balance start field is required and must be at least 0.';
        }
        if (isset($body['opening_notes']) && !is_string($body['opening_notes'])) {
            $errors['opening_notes'][] = 'The opening notes must be a string.';
        }
        if ($errors !== []) {
            throw new \app\components\ValidationException($errors);
        }

        $session->cash_balance_start = (float) $body['cash_balance_start'];
        $session->opening_notes = $body['opening_notes'] ?? null;
        $session->state = PosSession::STATE_OPENED;
        $session->save(false);

        return [
            'data' => $this->sessionData($session),
            'message' => 'Opening control confirmed successfully.',
        ];
    }

    public function actionClosingControl($session)
    {
        $this->requirePermission('create_point_of_sale_session', 'view_any_point_of_sale_session');
        $session = $this->findSession($session);

        if ($session->state === PosSession::STATE_CLOSED) {
            throw new BadRequestHttpException('This session is already closed.');
        }

        $session->state = PosSession::STATE_CLOSING_CONTROL;
        $session->save(false);

        return [
            'data' => $this->sessionData($session),
            'message' => 'Closing control requested successfully.',
        ];
    }

    public function actionCloseSession($session)
    {
        $this->requirePermission('create_point_of_sale_session', 'view_any_point_of_sale_session');
        $session = $this->findSession($session);

        if ($session->state === PosSession::STATE_CLOSED) {
            throw new BadRequestHttpException('This session is already closed.');
        }

        $draftOrders = (int) PosOrder::find()
            ->where(['session_id' => $session->id, 'state' => PosOrder::STATE_DRAFT])
            ->count();
        if ($draftOrders > 0) {
            Yii::$app->response->setStatusCode(422);
            return [
                'message' => 'This session still has draft orders.',
                'code' => 'draft-orders',
            ];
        }

        $body = $this->body();
        $errors = [];
        if (isset($body['cash_balance_end_real']) && !is_numeric($body['cash_balance_end_real'])) {
            $errors['cash_balance_end_real'][] = 'The cash balance end real field must be a number.';
        }
        if ($errors !== []) {
            throw new \app\components\ValidationException($errors);
        }

        $expected = self::float($session->cash_balance_start) + self::float($session->cash_transaction_total);
        $counted = isset($body['cash_balance_end_real']) ? (float) $body['cash_balance_end_real'] : $expected;
        $difference = round($counted - $expected, 2);

        $config = $session->config_id !== null ? PosConfig::findOne(['id' => $session->config_id]) : null;
        if ($config !== null && (bool) $config->enable_maximum_difference && $config->amount_authorized_diff !== null) {
            if (abs($difference) > self::float($config->amount_authorized_diff)) {
                Yii::$app->response->setStatusCode(422);
                return [
                    'message' => 'The cash difference exceeds the authorized amount.',
                    'code' => 'unbalanced',
                    'delta' => $difference,
                ];
            }
        }

        $session->cash_balance_end = $expected;
        $session->cash_balance_end_real = $counted;
        $session->cash_difference = $difference;
        $session->state = PosSession::STATE_CLOSED;
        $session->stopped_at = date('Y-m-d H:i:s');
        $session->closed_by_id = Yii::$app->access->currentUserId();
        if (isset($body['closing_notes'])) {
            $session->closing_notes = $body['closing_notes'];
        }
        $session->save(false);

        return [
            'data' => $this->sessionData($session),
            'message' => 'Session closed successfully.',
        ];
    }

    public function actionCashMovement($session)
    {
        $this->requirePermission('create_point_of_sale_session', 'view_any_point_of_sale_session');
        $session = $this->findSession($session);

        if ($session->state === PosSession::STATE_CLOSED) {
            throw new BadRequestHttpException('This session is already closed.');
        }

        $body = $this->body();
        $errors = [];
        $type = $body['type'] ?? null;
        if (!in_array($type, [PosCashMovement::TYPE_IN, PosCashMovement::TYPE_OUT], true)) {
            $errors['type'][] = 'The selected type is invalid.';
        }
        if (!isset($body['amount']) || !is_numeric($body['amount']) || (float) $body['amount'] <= 0) {
            $errors['amount'][] = 'The amount field must be greater than 0.';
        }
        if ($errors !== []) {
            throw new \app\components\ValidationException($errors);
        }

        $userId = Yii::$app->access->currentUserId();
        $amount = (float) $body['amount'];

        $movement = new PosCashMovement();
        $movement->setAttributes([
            'type' => $type,
            'amount' => $amount,
            'reason' => $body['reason'] ?? null,
            'moved_at' => date('Y-m-d H:i:s'),
            'session_id' => $session->id,
            'user_id' => $userId,
            'company_id' => $session->company_id,
            'creator_id' => $userId,
        ], false);
        $movement->save(false);

        $session->cash_transaction_total = self::float($session->cash_transaction_total)
            + ($type === PosCashMovement::TYPE_IN ? $amount : -$amount);
        $session->save(false);

        Yii::$app->response->setStatusCode(201);

        return [
            'data' => [
                'id' => (int) $movement->id,
                'type' => $movement->type,
                'amount' => self::float($movement->amount),
                'reason' => $movement->reason,
            ],
            'message' => 'Cash movement recorded successfully.',
        ];
    }

    public function actionOrders($session)
    {
        $this->requirePermission('view_any_point_of_sale_order');
        $session = $this->findSession($session);

        $search = trim((string) Yii::$app->request->get('search', ''));
        $status = (string) Yii::$app->request->get('status', 'active');
        $page = max(1, (int) Yii::$app->request->get('page', 1) ?: 1);
        $perPage = min(100, max(1, (int) Yii::$app->request->get('per_page', 20) ?: 20));

        $query = PosOrder::find()->where(['session_id' => $session->id]);

        if ($status === 'active') {
            $query->andWhere(['<>', 'state', PosOrder::STATE_CANCELED]);
        } else {
            $query->andWhere(['state' => $status]);
        }

        if ($search !== '') {
            $partnerIds = (new Query())->from('partners_partners')
                ->select('id')->where(['like', 'name', $search])->column();
            $query->andWhere(['or',
                ['like', 'name', $search],
                ['like', 'reference', $search],
                ['like', 'receipt_code', $search],
                $partnerIds === [] ? ['id' => -1] : ['partner_id' => array_map('intval', $partnerIds)],
            ]);
        }

        $total = (int) (clone $query)->count();
        $orders = $query
            ->with(['partner', 'user', 'currency', 'lines.product', 'lines.taxes', 'payments.paymentMethod'])
            ->orderBy(['id' => SORT_DESC])
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->all();

        $data = array_map(fn (PosOrder $order) => $this->orderData($order), $orders);

        return $this->envelope($data, $page, $perPage, $total);
    }

    public function actionSalesDetails($session)
    {
        $this->requirePermission('view_any_point_of_sale_session');
        $session = $this->findSession($session);

        $orders = PosOrder::find()
            ->where(['session_id' => $session->id])
            ->andWhere(['<>', 'state', PosOrder::STATE_CANCELED])
            ->all();

        $untaxed = 0.0;
        $tax = 0.0;
        $total = 0.0;
        foreach ($orders as $order) {
            $untaxed += self::float($order->amount_untaxed);
            $tax += self::float($order->amount_tax);
            $total += self::float($order->amount_total);
        }

        $paymentRows = (new Query())
            ->from(['p' => 'pos_payments'])
            ->leftJoin('pos_payment_methods pm', 'pm.id = p.payment_method_id')
            ->where(['p.session_id' => $session->id, 'p.is_change' => 0])
            ->groupBy(['p.payment_method_id', 'pm.name'])
            ->select(['method_id' => 'p.payment_method_id', 'method' => 'pm.name', 'total' => 'SUM(p.amount)', 'count' => 'COUNT(*)'])
            ->all();

        return [
            'data' => [
                'session' => $this->sessionData($session),
                'currency' => $session->currency?->name ?? $session->config?->currency?->name,
                'order_count' => count($orders),
                'amount_untaxed' => round($untaxed, 2),
                'amount_tax' => round($tax, 2),
                'amount_total' => round($total, 2),
                'payments' => array_map(fn ($row) => [
                    'payment_method_id' => $row['method_id'] !== null ? (int) $row['method_id'] : null,
                    'payment_method' => $row['method'],
                    'total' => round((float) $row['total'], 2),
                    'count' => (int) $row['count'],
                ], $paymentRows),
            ],
        ];
    }

    public function actionClosingReport($session)
    {
        $this->requirePermission('view_any_point_of_sale_session');
        $session = $this->findSession($session);

        $movements = array_map(fn (PosCashMovement $movement) => [
            'id' => (int) $movement->id,
            'type' => $movement->type,
            'amount' => self::float($movement->amount),
            'reason' => $movement->reason,
            'moved_at' => self::iso($movement->moved_at),
        ], $session->cashMovements);

        $paymentRows = (new Query())
            ->from(['p' => 'pos_payments'])
            ->leftJoin('pos_payment_methods pm', 'pm.id = p.payment_method_id')
            ->where(['p.session_id' => $session->id])
            ->groupBy(['p.payment_method_id', 'pm.name'])
            ->select(['method_id' => 'p.payment_method_id', 'method' => 'pm.name', 'total' => 'SUM(p.amount)', 'count' => 'COUNT(*)'])
            ->all();

        return [
            'data' => [
                'session' => $this->sessionData($session),
                'expected_cash' => round(self::float($session->cash_balance_start) + self::float($session->cash_transaction_total), 2),
                'counted_cash' => $session->cash_balance_end_real === null ? null : self::float($session->cash_balance_end_real),
                'difference' => $session->cash_difference === null ? null : self::float($session->cash_difference),
                'cash_movements' => $movements,
                'payments' => array_map(fn ($row) => [
                    'payment_method_id' => $row['method_id'] !== null ? (int) $row['method_id'] : null,
                    'payment_method' => $row['method'],
                    'total' => round((float) $row['total'], 2),
                    'count' => (int) $row['count'],
                ], $paymentRows),
            ],
        ];
    }

    public function actionSyncOrders()
    {
        $this->requirePermission('create_point_of_sale_order', 'view_any_point_of_sale_order');

        $orders = $this->body()['orders'] ?? null;
        if (!is_array($orders) || $orders === []) {
            throw new ValidationException(['orders' => ['The orders field is required.']]);
        }

        $result = $this->orderService()->syncOrders($orders);

        return [
            'data' => array_map(fn (PosOrder $order) => $this->orderData($order), $result['data']),
            'errors' => $result['errors'],
        ];
    }

    public function actionRefundableLines($order)
    {
        $this->requirePermission('view_any_point_of_sale_order');
        $order = $this->findOrder($order);

        return ['data' => $this->orderService()->refundableLines($order)];
    }

    public function actionRefund($order)
    {
        $this->requirePermission('create_point_of_sale_order', 'view_any_point_of_sale_order');
        $order = $this->findOrder($order);

        $quantities = $this->body()['quantities'] ?? null;
        if (!is_array($quantities) || $quantities === []) {
            throw new ValidationException(['quantities' => ['The quantities field is required.']]);
        }

        return ['data' => $this->orderService()->pendingRefund($order, $quantities)];
    }

    public function actionInvoice($order)
    {
        $this->requirePermission('create_point_of_sale_order', 'view_any_point_of_sale_order');
        $order = $this->findOrder($order);

        $body = $this->body();
        if (!$order->partner_id && !empty($body['partner_id'])) {
            $order->partner_id = (int) $body['partner_id'];
            $order->save(false);
        }

        $order = $this->orderService()->invoice($order);

        return [
            'data' => $this->orderData($order),
            'message' => 'Order invoiced successfully.',
        ];
    }

    public function actionInvoicePdf($order)
    {
        $this->requirePermission('view_any_point_of_sale_order');
        $order = $this->findOrder($order);

        if ($order->account_move_id === null) {
            throw new NotFoundHttpException('Invoice not found.');
        }

        $response = Yii::$app->response;
        $response->format = Response::FORMAT_RAW;
        $response->headers->set('Content-Type', 'text/html; charset=UTF-8');

        return $this->orderService()->invoiceHtml($order);
    }

    public function actionReceipt($order)
    {
        $this->requirePermission('view_any_point_of_sale_order');
        $order = $this->findOrder($order);

        return ['data' => $this->orderService()->receipt($order)];
    }

    public function actionBoot($session)
    {
        $this->requirePermission('view_any_point_of_sale_session');
        $session = $this->findSession($session);

        $config = $session->config_id !== null ? PosConfig::findOne(['id' => (int) $session->config_id]) : null;
        if ($config === null) {
            throw new NotFoundHttpException('Register not found.');
        }

        $paymentMethods = (new Query())->from('pos_payment_methods')
            ->where(['deleted_at' => null])
            ->andWhere(['or', ['company_id' => $config->company_id], ['company_id' => null]])
            ->orderBy(['sort' => SORT_ASC, 'id' => SORT_ASC])
            ->select(['id', 'name', 'type', 'terminal_type', 'is_cash_count', 'is_split_transaction'])
            ->all();

        $drafts = PosOrder::find()
            ->where(['session_id' => $session->id, 'state' => PosOrder::STATE_DRAFT])
            ->orderBy(['id' => SORT_ASC])
            ->all();

        return [
            'data' => [
                'session' => $this->sessionData($session),
                'register' => $this->registerData($config, $session),
                'payment_methods' => array_map(fn ($method) => [
                    'id' => (int) $method['id'],
                    'name' => $method['name'],
                    'type' => $method['type'],
                    'terminal_type' => $method['terminal_type'],
                    'is_cash_count' => (bool) $method['is_cash_count'],
                    'is_split_transaction' => (bool) $method['is_split_transaction'],
                ], $paymentMethods),
                'catalog' => $this->catalogData($config),
                'drafts' => array_map(fn (PosOrder $order) => $this->orderData($order), $drafts),
            ],
        ];
    }

    public function actionCancelDraftOrders($session)
    {
        $this->requirePermission('create_point_of_sale_order', 'view_any_point_of_sale_session');
        $session = $this->findSession($session);

        $service = $this->orderService();
        $canceled = $service->cancelDraftOrders($session);
        $service->refreshSessionTotals($session);

        return ['data' => ['canceled' => $canceled]];
    }

    private function envelope(array $data, int $page, int $perPage, int $total): array
    {
        $lastPage = max(1, (int) ceil($total / $perPage));
        $request = Yii::$app->request;
        $base = strtok($request->getAbsoluteUrl(), '?');
        $queryParams = $request->getQueryParams();
        unset($queryParams['page'], $queryParams['session'], $queryParams['config']);
        $make = function (int $p) use ($base, $queryParams): string {
            $params = $queryParams;
            $params['page'] = $p;
            return $base . '?' . http_build_query($params);
        };

        return [
            'data' => $data,
            'links' => [
                'first' => $make(1),
                'last' => $make($lastPage),
                'prev' => $page > 1 ? $make($page - 1) : null,
                'next' => $page < $lastPage ? $make($page + 1) : null,
            ],
            'meta' => [
                'current_page' => $page,
                'from' => $total === 0 ? null : ($page - 1) * $perPage + 1,
                'last_page' => $lastPage,
                'path' => $base,
                'per_page' => $perPage,
                'to' => $total === 0 ? null : min($total, $page * $perPage),
                'total' => $total,
            ],
        ];
    }

    private static function iso($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        $timestamp = strtotime((string) $value);
        return $timestamp === false ? null : date('c', $timestamp);
    }

    private static function float($value): float
    {
        return $value === null ? 0.0 : (float) $value;
    }
}
