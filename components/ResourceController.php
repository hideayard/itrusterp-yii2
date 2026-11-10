<?php

namespace app\components;

use Yii;
use yii\db\ActiveQuery;
use yii\db\Query;
use yii\web\BadRequestHttpException;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;

class ResourceController extends ApiController
{
    private const ABILITIES = [
        'viewAny' => 'view_any',
        'view' => 'view',
        'create' => 'create',
        'update' => 'update',
        'delete' => 'delete',
        'restore' => 'restore',
        'forceDelete' => 'force_delete',
    ];

    public function beforeAction($action)
    {
        if (!parent::beforeAction($action)) {
            return false;
        }
        $cfg = Resources::get((string) Yii::$app->request->get('resource'));
        if ($cfg === null) {
            throw new NotFoundHttpException('Resource not found.');
        }
        if (isset($cfg['actions']) && !in_array($action->id, $cfg['actions'], true)) {
            throw new NotFoundHttpException('Resource not found.');
        }
        return true;
    }

    protected function cfg(): array
    {
        $cfg = Resources::get((string) Yii::$app->request->get('resource'));
        if ($cfg === null) {
            throw new NotFoundHttpException('Resource not found.');
        }
        return $cfg;
    }

    public function actionIndex()
    {
        $cfg = $this->cfg();
        $this->resolveParentIfNested($cfg);
        $this->authorize('viewAny', $cfg);
        $query = $this->baseQuery($cfg);
        $this->applyQueryOptions($query, $cfg);
        return $this->paginate($query, $cfg);
    }

    public function actionView($id)
    {
        $cfg = $this->cfg();
        $this->resolveParentIfNested($cfg);
        $this->authorize('view', $cfg);
        $this->resolveExpand($cfg);
        $model = $this->findModel($cfg, $id);
        return ['data' => $this->serialize($model, $cfg)];
    }

    public function actionCreate()
    {
        $cfg = $this->cfg();
        $this->resolveParentIfNested($cfg);
        $this->authorize('create', $cfg);
        $this->resolveExpand($cfg);

        [$input, $payload] = $this->parseBody($cfg);
        $this->validatePayload($cfg, $payload);

        $modelClass = $cfg['model'];
        $model = new $modelClass();

        if (isset($cfg['parentScope'])) {
            $model->setAttribute($cfg['parentScope']['fk'], (string) Yii::$app->request->get($cfg['parentScope']['param']));
        }
        foreach (($cfg['fixed'] ?? []) as $col => $val) {
            $model->setAttribute($col, $val === 'parent'
                ? (string) Yii::$app->request->get($cfg['parentScope']['param'])
                : $val);
        }

        $this->loadAttributes($model, $input);
        $this->validateModel($model, $input, false);
        $this->runHook($cfg, 'beforeSave', $model, $input, false);

        if (!$model->save(false)) {
            throw new ValidationException($model->getErrors());
        }
        $this->afterSave($cfg, $model, $payload);

        Yii::$app->response->setStatusCode(201);
        return [
            'data' => $this->serialize($model, $cfg, true),
            'message' => "{$cfg['label']} created successfully.",
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
        $this->validatePayload($cfg, $payload);
        $this->loadAttributes($model, $input);
        $this->validateModel($model, $input, true);
        $this->runHook($cfg, 'beforeSave', $model, $input, true);

        if (!$model->save(false)) {
            throw new ValidationException($model->getErrors());
        }
        $this->afterSave($cfg, $model, $payload);

        return [
            'data' => $this->serialize($model, $cfg, true),
            'message' => "{$cfg['label']} updated successfully.",
        ];
    }

    public function actionDelete($id)
    {
        $cfg = $this->cfg();
        $this->resolveParentIfNested($cfg);
        $this->authorize('delete', $cfg);
        $model = $this->findModel($cfg, $id);
        $model->delete();
        return ['message' => "{$cfg['label']} deleted successfully."];
    }

    public function actionRestore($id)
    {
        $cfg = $this->cfg();
        $this->resolveParentIfNested($cfg);
        $this->authorize('restore', $cfg);
        $this->resolveExpand($cfg);
        $model = $this->findModel($cfg, $id, true);
        $model->restore();
        return [
            'data' => $this->serialize($model, $cfg, true),
            'message' => "{$cfg['label']} restored successfully.",
        ];
    }

    public function actionForceDelete($id)
    {
        $cfg = $this->cfg();
        $this->resolveParentIfNested($cfg);
        $this->authorize('forceDelete', $cfg);
        $model = $this->findModel($cfg, $id, true);
        $model->forceDelete();
        return ['message' => "{$cfg['label']} permanently deleted."];
    }

    protected function resolveParentIfNested(array $cfg): array
    {
        if (!isset($cfg['parentScope'])) {
            return [null, null];
        }
        $ps = $cfg['parentScope'];
        $parentCfg = Resources::get($ps['resource']);
        $parentId = Yii::$app->request->get($ps['param']);
        $modelClass = $parentCfg['model'];
        $parent = $modelClass::find()->where(['id' => $parentId])->one();
        if ($parent === null) {
            throw new NotFoundHttpException('Resource not found.');
        }
        return [$parent, $parentCfg];
    }

    protected function baseQuery(array $cfg): ActiveQuery
    {
        $modelClass = $cfg['model'];
        $query = $modelClass::find();
        foreach ($cfg['baseWhere'] ?? [] as $col => $val) {
            $query->andWhere([$col => $val]);
        }
        if (isset($cfg['baseQuery']) && is_callable($cfg['baseQuery'])) {
            ($cfg['baseQuery'])($query);
        }
        if (isset($cfg['parentScope'])) {
            $query->andWhere([$cfg['parentScope']['fk'] => (string) Yii::$app->request->get($cfg['parentScope']['param'])]);
        }
        return $query;
    }

    protected function findModel(array $cfg, $id, bool $withTrashed = false)
    {
        $modelClass = $cfg['model'];
        $query = $modelClass::find();
        if ($withTrashed) {
            $query->withTrashed();
        }
        $query->andWhere([$modelClass::tableName() . '.id' => $id]);
        foreach ($cfg['baseWhere'] ?? [] as $col => $val) {
            $query->andWhere([$col => $val]);
        }
        if (isset($cfg['baseQuery']) && is_callable($cfg['baseQuery'])) {
            ($cfg['baseQuery'])($query);
        }
        if (isset($cfg['parentScope'])) {
            $query->andWhere([$cfg['parentScope']['fk'] => (string) Yii::$app->request->get($cfg['parentScope']['param'])]);
        }
        $model = $query->one();
        if ($model === null) {
            throw new NotFoundHttpException('Resource not found.');
        }
        return $model;
    }

    protected function authorize(string $ability, array $cfg): void
    {
        if (empty($cfg['permission'])) {
            return;
        }
        if (!Yii::$app->access->can($this->permissionName($ability, $cfg))) {
            throw new ForbiddenHttpException('This action is unauthorized.');
        }
    }

    protected function permissionName(string $ability, array $cfg): string
    {
        $ability = $cfg['abilityOverrides'][$ability] ?? $ability;
        $suffix = self::ABILITIES[$ability];
        if (($cfg['auth'] ?? 'self') === 'parent' && isset($cfg['parentScope'])) {
            $parentCfg = Resources::get($cfg['parentScope']['resource']);
            $parentAbility = $suffix;
            if (in_array($ability, ['viewAny', 'view'], true)) {
                $parentAbility = 'view';
            } elseif ($ability === 'delete' && isset($cfg['parentDeleteAbility'])) {
                $parentAbility = $cfg['parentDeleteAbility'];
            } elseif ($ability !== 'delete') {
                $parentAbility = 'update';
            }
            return $parentAbility . '_' . $parentCfg['permission'];
        }
        return $suffix . '_' . $cfg['permission'];
    }

    protected function parseBody(array $cfg): array
    {
        $body = Yii::$app->request->getBodyParams();
        if (!is_array($body)) {
            $body = [];
        }
        $modelClass = $cfg['model'];
        $writable = $modelClass::writableAttributes();
        $input = [];
        foreach ($body as $k => $v) {
            if (in_array((string) $k, $writable, true)) {
                $input[$k] = $v;
            }
        }
        $payload = [];
        foreach (array_keys($cfg['payload'] ?? []) as $key) {
            if (array_key_exists($key, $body)) {
                $payload[$key] = $body[$key];
            }
        }
        return [$input, $payload];
    }

    protected function loadAttributes(BaseModel $model, array $input): void
    {
        foreach ($input as $k => $v) {
            $model->setAttribute((string) $k, $v);
        }
    }

    protected function validateModel(BaseModel $model, array $input, bool $isUpdate): void
    {
        if ($isUpdate && $input === []) {
            return;
        }
        if (!$model->validate()) {
            throw new ValidationException($model->getErrors());
        }
    }

    protected function validatePayload(array $cfg, array $payload): void
    {
        $errors = [];
        foreach ($cfg['payload'] ?? [] as $key => $spec) {
            if (!array_key_exists($key, $payload) || $spec === 'nested') {
                continue;
            }
            $value = $payload[$key];
            [$op, $arg] = array_pad(explode(':', (string) $spec, 2), 2, null);
            if ($op === 'array_of_int') {
                if (!is_array($value)) {
                    $errors[$key][] = "The {$key} field must be an array.";
                    continue;
                }
                foreach ($value as $i => $item) {
                    if (!is_numeric($item) || (string) (int) $item !== (string) $item) {
                        $errors["{$key}.{$i}"][] = "The {$key}.{$i} field must be an integer.";
                    } elseif (!(new Query())->from((string) $arg)->where(['id' => (int) $item])->exists()) {
                        $errors["{$key}.{$i}"][] = "The selected {$key}.{$i} is invalid.";
                    }
                }
            }
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }
    }

    protected function afterSave(array $cfg, BaseModel $model, array $payload): void
    {
        foreach ($cfg['afterSave'] ?? [] as $hook) {
            if ($hook === 'sync_tags' && array_key_exists('tags', $payload)) {
                $pivot = $cfg['tagPivot'];
                $db = Yii::$app->db;
                $db->createCommand()->delete($pivot['table'], [$pivot['fk'] => $model->id])->execute();
                $ids = array_values(array_unique(array_map('intval', (array) $payload['tags'])));
                foreach ($ids as $tagId) {
                    $db->createCommand()->insert($pivot['table'], [
                        $pivot['fk'] => $model->id,
                        $pivot['ref'] => $tagId,
                    ])->execute();
                }
            }
        }

        foreach ($cfg['pivots'] ?? [] as $pivot) {
            if (!array_key_exists($pivot['payload'], $payload)) {
                continue;
            }
            $db = Yii::$app->db;
            $filter = array_merge([$pivot['fk'] => $model->id], $pivot['where'] ?? []);
            $db->createCommand()->delete($pivot['table'], $filter)->execute();
            $ids = array_values(array_unique(array_map('intval', (array) $payload[$pivot['payload']])));
            foreach ($ids as $refId) {
                $db->createCommand()->insert($pivot['table'], array_merge($pivot['extra'] ?? [], [
                    $pivot['fk'] => $model->id,
                    $pivot['ref'] => $refId,
                ]))->execute();
            }
        }
    }

    protected function runHook(array $cfg, string $name, BaseModel $model, array $input, bool $isUpdate): void
    {
        $hook = $cfg['hooks'][$name] ?? null;
        if ($hook === null) {
            return;
        }
        $hook($model, $input, $isUpdate);
    }

    protected function applyQueryOptions(ActiveQuery $query, array $cfg): void
    {
        $request = Yii::$app->request;

        $filters = $request->get('filter');
        if (is_array($filters)) {
            foreach ($cfg['exact'] ?? [] as $col) {
                if (isset($filters[$col]) && $filters[$col] !== '') {
                    $vals = is_array($filters[$col]) ? $filters[$col] : explode(',', (string) $filters[$col]);
                    $query->andWhere(['in', $col, $vals]);
                }
            }
            foreach ($cfg['partial'] ?? [] as $col) {
                if (!empty($filters[$col])) {
                    $query->andWhere(['like', $col, (string) $filters[$col]]);
                }
            }
            if (!empty($cfg['soft']) && isset($filters['trashed'])) {
                if ($filters['trashed'] === 'with') {
                    $query->withTrashed();
                } elseif ($filters['trashed'] === 'only') {
                    $query->onlyTrashed();
                }
            }
        }

        $sort = $request->get('sort');
        if ($sort !== null && $sort !== '') {
            $allowed = $cfg['sorts'] ?? [];
            $orders = [];
            foreach (explode(',', (string) $sort) as $s) {
                $s = trim($s);
                if ($s === '') {
                    continue;
                }
                $dir = SORT_ASC;
                if (str_starts_with($s, '-')) {
                    $dir = SORT_DESC;
                    $s = substr($s, 1);
                }
                if (!in_array($s, $allowed, true)) {
                    throw new BadRequestHttpException("The sort [{$s}] is not allowed.");
                }
                $orders[$s] = $dir;
            }
            if ($orders !== []) {
                $query->addOrderBy($orders);
            }
        }

        $this->resolveExpand($cfg);
        $this->eagerLoad($query, $cfg);
    }

    protected function resolveExpand(array $cfg): void
    {
        $this->expand = [];
        $includes = Yii::$app->request->get('include');
        if ($includes !== null && $includes !== '') {
            $allowed = $this->normalizeIncludes($cfg['includes'] ?? []);
            foreach (explode(',', (string) $includes) as $inc) {
                $inc = trim($inc);
                if ($inc === '') {
                    continue;
                }
                $normalized = $this->normalizeInclude($inc);
                if (!in_array($normalized, $allowed, true)) {
                    throw new BadRequestHttpException("The include [{$inc}] is not allowed.");
                }
                $this->expand[] = $normalized;
            }
        }
        foreach ($cfg['defaultIncludes'] ?? [] as $d) {
            if (!in_array($d, $this->expand, true)) {
                $this->expand[] = $d;
            }
        }
    }

    protected function eagerLoad(ActiveQuery $query, array $cfg): void
    {
        $with = [];
        foreach ($this->expand as $e) {
            $root = explode('.', (string) $e)[0];
            $with[] = $cfg['model']::apiRelations()[$root] ?? $root;
        }
        if ($with !== []) {
            $query->with(array_values(array_unique($with)));
        }
    }

    private function normalizeIncludes(array $includes): array
    {
        return array_values(array_map(fn ($i) => $this->normalizeInclude((string) $i), $includes));
    }

    private function normalizeInclude(string $s): string
    {
        $segments = explode('.', $s);
        foreach ($segments as &$seg) {
            $seg = strtolower((string) preg_replace('/(?<=[a-z0-9])([A-Z])/', '_$1', $seg));
        }
        return implode('.', $segments);
    }

    protected function paginate(ActiveQuery $query, array $cfg): array
    {
        $page = max(1, (int) Yii::$app->request->get('page', 1) ?: 1);
        $perPage = min(100, max(1, (int) Yii::$app->request->get('per_page', 15) ?: 15));
        $total = (int) $query->count();
        $models = $query->offset(($page - 1) * $perPage)->limit($perPage)->all();

        $lastPage = max(1, (int) ceil($total / $perPage));
        $base = strtok(Yii::$app->request->getAbsoluteUrl(), '?');
        $queryParams = Yii::$app->request->getQueryParams();
        unset($queryParams['page'], $queryParams['resource']);
        $make = function (int $p) use ($base, $queryParams): string {
            $params = $queryParams;
            $params['page'] = $p;
            return $base . '?' . http_build_query($params);
        };

        return [
            'data' => array_map(fn ($m) => $this->serialize($m, $cfg), $models),
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

    protected function serialize(BaseModel $model, array $cfg, bool $isWrite = false): array
    {
        $expand = $this->expand;
        if ($isWrite) {
            $expand = array_unique(array_merge($expand, $cfg['writeIncludes'] ?? []));
        }
        return $model->toArray([], array_values($expand));
    }

    protected function serializeCollection(array $models, array $cfg): array
    {
        return array_map(fn ($m) => $this->serialize($m, $cfg), $models);
    }

    protected function paginatedEnvelope(array $data, int $page, int $perPage, int $total): array
    {
        $lastPage = max(1, (int) ceil($total / $perPage));
        $request = Yii::$app->request;
        $base = strtok($request->getAbsoluteUrl(), '?');
        $queryParams = $request->getQueryParams();
        unset($queryParams['page'], $queryParams['resource']);
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
}
