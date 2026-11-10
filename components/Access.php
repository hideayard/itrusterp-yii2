<?php

namespace app\components;

use Yii;
use yii\db\Query;
use app\models\User;

class Access
{
    public const USER_TYPE = 'Webkul\Security\Models\User';

    private ?User $user = null;
    private ?array $permissions = null;
    private ?array $allowedCompanyIds = null;
    private ?array $activeCompanyIds = null;
    private ?array $authorizedUserIds = null;
    public ?int $tokenId = null;

    public function setUser(?User $user): void
    {
        $this->user = $user;
        $this->permissions = null;
        $this->allowedCompanyIds = null;
        $this->activeCompanyIds = null;
        $this->authorizedUserIds = null;
    }

    public function user(): ?User
    {
        return $this->user;
    }

    public function currentUserId(): ?int
    {
        return $this->user?->id;
    }

    public function bypassScopes(): bool
    {
        return $this->user !== null && $this->user->hasRole('super_admin');
    }

    public function can(string $ability): bool
    {
        if ($this->user === null) {
            return false;
        }
        if ($this->user->hasRole('super_admin')) {
            return true;
        }
        return in_array($ability, $this->permissions(), true);
    }

    public function permissions(): array
    {
        if ($this->permissions === null) {
            $type = self::USER_TYPE;
            $typeDoubled = 'Webkul\\Security\\Models\\User';
            $uid = (string) $this->user->id;
            $sql = "SELECT p.name FROM permissions p
                    INNER JOIN role_has_permissions rhp ON rhp.permission_id = p.id
                    INNER JOIN model_has_roles mhr ON mhr.role_id = rhp.role_id
                    WHERE mhr.model_type IN (:type, :type2) AND mhr.model_id = :uid
                    UNION
                    SELECT p2.name FROM permissions p2
                    INNER JOIN model_has_permissions mhp ON mhp.permission_id = p2.id
                    WHERE mhp.model_type IN (:type, :type2) AND mhp.model_id = :uid";
            $this->permissions = Yii::$app->db->createCommand($sql, [
                ':type' => $type,
                ':type2' => $typeDoubled,
                ':uid' => $uid,
            ])->queryColumn();
        }
        return $this->permissions;
    }

    public function allowedCompanyIds(): array
    {
        if ($this->allowedCompanyIds === null) {
            if ($this->user === null) {
                $this->allowedCompanyIds = [];
            } elseif ($this->bypassScopes()) {
                $this->allowedCompanyIds = array_map('intval', (new Query())
                    ->from('companies')->select('id')->column());
            } else {
                $this->allowedCompanyIds = array_map('intval', (new Query())
                    ->from('user_allowed_companies')
                    ->select('company_id')
                    ->where(['user_id' => $this->user->id])
                    ->column());
            }
        }
        return $this->allowedCompanyIds;
    }

    public function activeCompanyIds(): array
    {
        if ($this->activeCompanyIds === null) {
            $allowed = $this->allowedCompanyIds();
            if ($allowed === []) {
                $this->activeCompanyIds = [];
            } elseif ($this->user->default_company_id !== null
                && in_array((int) $this->user->default_company_id, $allowed, true)
            ) {
                $this->activeCompanyIds = [(int) $this->user->default_company_id];
            } else {
                $this->activeCompanyIds = [reset($allowed)];
            }
        }
        return $this->activeCompanyIds;
    }

    public function authorizedUserIds(): ?array
    {
        if ($this->authorizedUserIds === null) {
            $user = $this->user;
            if ($user === null) {
                $this->authorizedUserIds = null;
            } elseif ($user->resource_permission === 'global') {
                $this->authorizedUserIds = null;
            } elseif ($user->resource_permission === 'group') {
                $teamIds = (new Query())
                    ->from('user_team')->select('team_id')
                    ->where(['user_id' => $user->id])->column();
                $this->authorizedUserIds = $teamIds === []
                    ? []
                    : array_map('intval', (new Query())
                        ->from(['u' => 'users'])
                        ->select('u.id')
                        ->leftJoin('user_team ut', 'ut.user_id = u.id')
                        ->leftJoin('teams t', 't.id = ut.team_id')
                        ->where(['in', 't.id', $teamIds])
                        ->column());
            } else {
                $this->authorizedUserIds = [(int) $user->id];
            }
        }
        return $this->authorizedUserIds;
    }
}
