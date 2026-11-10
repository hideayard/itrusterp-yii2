<?php

namespace app\components;

use Yii;
use yii\db\ActiveQuery;

class ScopedQuery extends ActiveQuery
{
    public string $trashedMode = 'without';
    public bool $scopesOff = false;
    public bool $accessScopesOff = false;
    private bool $scoped = false;

    public function withTrashed(): self
    {
        $this->trashedMode = 'with';
        return $this;
    }

    public function onlyTrashed(): self
    {
        $this->trashedMode = 'only';
        return $this;
    }

    public function withoutScopesAll(): self
    {
        $this->scopesOff = true;
        return $this;
    }

    /**
     * Hanya mematikan scope akses (company/allowed-company/ownership) —
     * dipakai saat memuat relasi (mis. manager, partner, user, warehouse)
     * yang tidak boleh difilter oleh konteks akses pemanggil.
     */
    public function withoutAccessScopes(): self
    {
        $this->accessScopesOff = true;
        return $this;
    }

    public function prepare($builder)
    {
        if (!$this->scoped && !$this->scopesOff) {
            $this->scoped = true;
            $model = new $this->modelClass;
            $this->applySoftDelete($model);
            if (!$this->accessScopesOff) {
                $this->applyCompanyScope($model);
                $this->applyAllowedCompanyScope($model);
                $this->applyOwnershipScope($model);
            }
        }
        return parent::prepare($builder);
    }

    private function applySoftDelete($model): void
    {
        if (!$model::isSoftDelete()) {
            return;
        }
        $col = $model::tableName() . '.deleted_at';
        if ($this->trashedMode === 'without') {
            $this->andWhere([$col => null]);
        } elseif ($this->trashedMode === 'only') {
            $this->andWhere(['not', [$col => null]]);
        }
    }

    private function applyCompanyScope($model): void
    {
        if (!$model::isCompanyScoped()) {
            return;
        }
        $access = Yii::$app->get('access', false);
        if (!$access || $access->user() === null || $access->bypassScopes()) {
            return;
        }
        $col = $model::tableName() . '.company_id';
        $ids = $access->activeCompanyIds();
        if ($ids !== []) {
            $this->andWhere(['or', ['in', $col, $ids], [$col => null]]);
        } else {
            $this->andWhere([$col => null]);
        }
    }

    private function applyAllowedCompanyScope($model): void
    {
        if (!$model::isAllowedCompanyScoped()) {
            return;
        }
        $access = Yii::$app->get('access', false);
        if (!$access || $access->user() === null || $access->bypassScopes()) {
            return;
        }
        $ids = $access->allowedCompanyIds();
        if ($ids === []) {
            $this->andWhere('0=1');
            return;
        }
        $this->andWhere(['in', $model::tableName() . '.id', $ids]);
    }

    private function applyOwnershipScope($model): void
    {
        if (!$model::isOwnershipScoped()) {
            return;
        }
        $access = Yii::$app->get('access', false);
        if (!$access || $access->user() === null || $access->bypassScopes()) {
            return;
        }
        $userIds = $access->authorizedUserIds();
        if ($userIds === null) {
            return;
        }
        if ($userIds === []) {
            $this->andWhere('0=1');
            return;
        }
        $table = $model::tableName();
        $or = ['or'];
        foreach ($model::ownershipSources() as $col) {
            $or[] = ['in', $table . '.' . $col, $userIds];
        }
        $this->andWhere($or);
    }
}
