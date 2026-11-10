<?php

namespace app\models;

use Yii;
use yii\db\Query;
use yii\web\IdentityInterface;
use app\components\BaseModel;

class User extends BaseModel implements IdentityInterface
{
    public const LARAVEL_TYPE = 'Webkul\Security\Models\User';

    public static function tableName(): string
    {
        return 'users';
    }

    public static function isSoftDelete(): bool
    {
        return true;
    }

    public static function isOwnershipScoped(): bool
    {
        return true;
    }

    public static function ownershipSources(): array
    {
        return ['creator_id', 'id'];
    }

    public static function findIdentity($id): ?self
    {
        return static::findUnscoped()->where(['id' => $id])->one();
    }

    public static function findIdentityByAccessToken($token, $type = null): ?self
    {
        if (!is_string($token) || strpos($token, '|') === false) {
            return null;
        }
        [$tokenId, $plain] = explode('|', $token, 2);
        $row = (new Query())->from('personal_access_tokens')
            ->where(['id' => (int) $tokenId])->one();
        if (empty($row)) {
            return null;
        }
        if (!hash_equals((string) $row['token'], hash('sha256', $plain))) {
            return null;
        }
        $validTypes = [self::LARAVEL_TYPE, 'Webkul\\Security\\Models\\User'];
        if (!in_array((string) $row['tokenable_type'], $validTypes, true)) {
            return null;
        }
        if ((int) $row['tokenable_id'] === 0) {
            return null;
        }
        if ($row['expires_at'] !== null && strtotime((string) $row['expires_at']) < time()) {
            return null;
        }
        $user = static::findUnscoped()->where(['id' => $row['tokenable_id']])->one();
        if ($user === null) {
            return null;
        }
        if ($row['last_used_at'] === null || strtotime((string) $row['last_used_at']) < time() - 60) {
            Yii::$app->db->createCommand()
                ->update('personal_access_tokens', ['last_used_at' => gmdate('Y-m-d H:i:s')], ['id' => $row['id']])
                ->execute();
        }
        Yii::$app->access->setUser($user);
        Yii::$app->access->tokenId = (int) $row['id'];
        return $user;
    }

    public function getId()
    {
        return $this->getPrimaryKey();
    }

    public function getAuthKey()
    {
        return $this->remember_token;
    }

    public function validateAuthKey($authKey): bool
    {
        return true;
    }

    public function getRoles()
    {
        return $this->hasMany(Role::class, ['id' => 'role_id'])
            ->viaTable('model_has_roles', ['model_id' => 'id']);
    }

    public function beforeSave($insert)
    {
        if (!parent::beforeSave($insert)) {
            return false;
        }

        $this->is_active ??= 1;
        $this->is_default ??= 0;
        $this->has_email_authentication ??= 1;

        if ($insert && !$this->password) {
            throw new \app\components\ValidationException(['password' => ['The password field is required.']]);
        }

        if ($this->password && $this->isAttributeChanged('password')
            && !preg_match('/^\$2[aby]\$|^\$argon2/', (string) $this->password)
        ) {
            $this->password = password_hash((string) $this->password, PASSWORD_BCRYPT, ['cost' => 12]);
        }

        return true;
    }

    public function hasRole(string|array $names): bool
    {
        $roleIds = (new Query())->from('model_has_roles')
            ->select('role_id')
            ->where(['model_type' => self::LARAVEL_TYPE, 'model_id' => $this->id])
            ->column();
        if ($roleIds === []) {
            return false;
        }
        $found = (new Query())->from('roles')
            ->select('name')
            ->where(['in', 'id', $roleIds])
            ->column();
        foreach ((array) $names as $name) {
            if (in_array($name, $found, true)) {
                return true;
            }
        }
        return false;
    }

    public function getPartner()
    {
        return $this->hasOne(Partner::class, ['id' => 'partner_id']);
    }

    public function getAllowedCompanies()
    {
        return $this->hasMany(Company::class, ['id' => 'company_id'])
            ->viaTable('user_allowed_companies', ['user_id' => 'id']);
    }

    public function getTeams()
    {
        return $this->hasMany(Team::class, ['id' => 'team_id'])
            ->viaTable('user_team', ['user_id' => 'id']);
    }

    public static function apiFields(): array
    {
        return [
            'id', 'name', 'username', 'email', 'language', 'is_active', 'is_default',
            'default_company_id', 'partner_id', 'created_at', 'updated_at', 'deleted_at',
        ];
    }

    public static function apiComputed(): array
    {
        return ['avatar' => fn () => null];
    }

    public static function apiCasts(): array
    {
        return [
            'is_active' => 'bool',
            'is_default' => 'bool',
            'created_at' => 'iso',
            'updated_at' => 'iso',
            'deleted_at' => 'iso',
        ];
    }

    public static function apiRelations(): array
    {
        return ['partner' => 'partner', 'roles' => 'roles'];
    }

    public static function apiRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'username' => ['string', 'max:64', 'unique:users,username'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['string', 'min:6'],
            'language' => ['string'],
            'is_active' => ['boolean'],
            'is_default' => ['boolean'],
            'default_company_id' => ['integer', 'exists:companies'],
            'partner_id' => ['integer', 'exists:partners_partners'],
            'resource_permission' => ['in:individual,group,global'],
        ];
    }
}
