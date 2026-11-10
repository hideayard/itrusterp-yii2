<?php

namespace app\components;

use Yii;
use yii\db\ActiveRecord;

abstract class BaseModel extends ActiveRecord
{
    public static function find(): ScopedQuery
    {
        return new ScopedQuery(static::class);
    }

    public static function findUnscoped(): ScopedQuery
    {
        return (new ScopedQuery(static::class))->withoutScopesAll();
    }

    /**
     * Yii2 membangun query relasi lewat `$class::find()`, sehingga relasi ikut
     * terkena scope akses (company/allowed-company/ownership) milik model tujuan
     * dan hasilnya bisa NULL walaupun foreign key-nya valid. Relasi di sini hanya
     * untuk kebutuhan tampilan; kontrol akses tetap dijaga oleh resource utama.
     * Soft-delete tetap berlaku.
     */
    protected function createRelationQuery($class, $link, $multiple)
    {
        $query = is_subclass_of($class, self::class)
            ? $class::find()->withoutAccessScopes()
            : $class::find();
        $query->primaryModel = $this;
        $query->link = $link;
        $query->multiple = $multiple;

        return $query;
    }

    public static function isSoftDelete(): bool
    {
        return false;
    }

    public static function isCompanyScoped(): bool
    {
        return false;
    }

    public static function isAllowedCompanyScoped(): bool
    {
        return false;
    }

    public static function isOwnershipScoped(): bool
    {
        return false;
    }

    public static function ownershipSources(): array
    {
        return ['creator_id'];
    }

    public static function autoAssignCompany(): bool
    {
        return false;
    }

    public static function apiFields(): array
    {
        return [];
    }

    public static function apiComputed(): array
    {
        return [];
    }

    public static function apiCasts(): array
    {
        return [];
    }

    public static function apiRelations(): array
    {
        return [];
    }

    public static function apiRules(): array
    {
        return [];
    }

    public static function writableAttributes(): array
    {
        return array_keys(static::apiRules());
    }

    public function fields(): array
    {
        $fields = [];
        foreach (static::apiFields() as $key) {
            $fields[$key] = function ($model) use ($key) {
                return $model->castOutput($key, $model->getAttribute($key));
            };
        }
        foreach (static::apiComputed() as $key => $cb) {
            $fields[$key] = function ($model) use ($cb) {
                return $cb($model);
            };
        }
        return $fields;
    }

    public function extraFields(): array
    {
        return static::apiRelations();
    }

    public function castOutput(string $key, $value)
    {
        if ($value === null) {
            return null;
        }
        if ($key === 'id') {
            return (int) $value;
        }
        return match (static::apiCasts()[$key] ?? null) {
            'bool' => (bool) $value,
            'float' => (float) $value,
            'int' => (int) $value,
            'iso' => static::toIso($value),
            'json' => is_string($value) ? json_decode($value, true) : $value,
            'translated' => static::translatedValue($value),
            default => $value,
        };
    }

    /**
     * Kolom JSON multi-bahasa (spatie/laravel-translatable) dibaca sebagai string
     * untuk locale yang diminta — sama seperti `$this->title` pada resource Laravel.
     */
    public static function translatedValue($value)
    {
        $bag = is_string($value) ? json_decode($value, true) : $value;
        if (!is_array($bag)) {
            return $value;
        }
        $locale = 'en';
        if (Yii::$app->has('request') && !Yii::$app->request->getIsConsoleRequest()) {
            $locale = (string) (Yii::$app->request->get('locale') ?: 'en');
        }
        if (array_key_exists($locale, $bag)) {
            return $bag[$locale];
        }
        $first = reset($bag);

        return $first === false ? null : $first;
    }

    public static function toIso($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        $ts = is_numeric($value) ? (int) $value : strtotime((string) $value);
        if ($ts === false) {
            return (string) $value;
        }
        return gmdate('Y-m-d\TH:i:s.000000\Z', $ts);
    }

    public function rules(): array
    {
        $rules = [];
        foreach (static::apiRules() as $attr => $specs) {
            foreach ((array) $specs as $spec) {
                $rule = $this->buildRule((string) $attr, (string) $spec);
                if ($rule !== []) {
                    $rules[] = $rule;
                }
            }
        }
        return $rules;
    }

    private function buildRule(string $attr, string $spec): array
    {
        [$op, $arg] = array_pad(explode(':', $spec, 2), 2, null);
        return match ($op) {
            'required' => [[$attr], 'required', 'message' => "The {$attr} field is required."],
            'string' => [[$attr], 'string', 'message' => "The {$attr} field must be a string."],
            'max' => [[$attr], 'string', 'max' => (int) $arg, 'tooLong' => "The {$attr} field must not exceed {$arg} characters."],
            'min' => [[$attr], 'string', 'min' => (int) $arg, 'tooShort' => "The {$attr} field must be at least {$arg} characters."],
            'email' => [[$attr], 'email', 'message' => "The {$attr} field must be a valid email address."],
            'url' => [[$attr], 'url', 'message' => "The {$attr} field must be a valid URL."],
            'integer' => [[$attr], 'integer', 'message' => "The {$attr} field must be an integer."],
            'boolean' => [[$attr], 'boolean', 'message' => "The {$attr} field must be true or false."],
            'number' => [[$attr], 'number', 'message' => "The {$attr} field must be a number."],
            'min_num' => [[$attr], 'number', 'min' => (float) $arg, 'tooSmall' => "The {$attr} field must be at least {$arg}."],
            'max_num' => [[$attr], 'number', 'max' => (float) $arg, 'tooBig' => "The {$attr} field must not exceed {$arg}."],
            'gt' => [[$attr], 'number', 'greaterThan' => (float) $arg, 'message' => "The {$attr} field must be greater than {$arg}."],
            'in' => [[$attr], 'in', 'range' => explode(',', (string) $arg), 'message' => "The selected {$attr} is invalid."],
            'exists' => [[$attr], Validators\RawExistsValidator::class, 'table' => $arg, 'message' => "The selected {$attr} is invalid."],
            'unique' => [[$attr], Validators\RawUniqueValidator::class, 'table' => $arg, 'message' => "The {$attr} has already been taken."],
            'barcode' => [[$attr], Validators\BarcodeUniqueValidator::class, 'owner' => $arg, 'message' => 'The barcode has already been taken.'],
            'company_allowed' => [[$attr], Validators\CompanyAllowedValidator::class, 'message' => 'The selected company is not allowed.'],
            'date' => [[$attr], Validators\LooseDateValidator::class, 'message' => "The {$attr} field must be a valid date."],
            'regex' => [[$attr], 'match', 'pattern' => (string) $arg, 'message' => "The {$attr} format is invalid."],
            'array_of_int' => [[$attr], Validators\ArrayValidator::class, 'itemType' => 'integer', 'table' => $arg, 'message' => "The {$attr} field is invalid."],
            'array_of_string' => [[$attr], Validators\ArrayValidator::class, 'itemType' => 'string', 'message' => "The {$attr} field must be an array of strings."],
            default => [],
        };
    }

    public function beforeSave($insert)
    {
        if (!parent::beforeSave($insert)) {
            return false;
        }
        $now = gmdate('Y-m-d H:i:s');
        $schema = static::getTableSchema();
        if ($insert) {
            if (isset($schema->columns['created_at']) && $this->getAttribute('created_at') === null) {
                $this->setAttribute('created_at', $now);
            }
            if (isset($schema->columns['creator_id']) && empty($this->getAttribute('creator_id'))) {
                $this->setAttribute('creator_id', Yii::$app->access->currentUserId());
            }
            if (isset($schema->columns['company_id'])
                && $this->getAttribute('company_id') === null
                && static::autoAssignCompany()
            ) {
                $ids = Yii::$app->access->activeCompanyIds();
                if ($ids !== []) {
                    $this->setAttribute('company_id', $ids[0]);
                }
            }
        }
        if (isset($schema->columns['updated_at'])) {
            $this->setAttribute('updated_at', $now);
        }
        foreach ($schema->columns as $column) {
            $value = $this->getAttribute($column->name);
            if ($value === null || $value === '') {
                continue;
            }
            $type = strtolower((string) $column->dbType);
            if ($type === 'date') {
                $ts = strtotime((string) $value);
                if ($ts !== false) {
                    $this->setAttribute($column->name, date('Y-m-d', $ts));
                }
            } elseif ($type === 'datetime' && !is_int($value)) {
                $ts = strtotime((string) $value);
                if ($ts !== false) {
                    $this->setAttribute($column->name, date('Y-m-d H:i:s', $ts));
                }
            }
        }
        return true;
    }

    public function afterFind()
    {
        parent::afterFind();
        foreach (static::getTableSchema()->columns as $column) {
            if (stripos((string) $column->dbType, 'json') !== false) {
                $value = $this->getAttribute($column->name);
                if (is_string($value) && $value !== '') {
                    $decoded = json_decode($value, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $this->setAttribute($column->name, $decoded);
                    }
                }
            }
        }
    }

    public function delete()
    {
        if (!static::isSoftDelete()) {
            return parent::delete();
        }
        $this->setAttribute('deleted_at', gmdate('Y-m-d H:i:s'));
        return $this->update(false) !== false;
    }

    public function forceDelete()
    {
        return parent::delete();
    }

    public function restore(): bool
    {
        $this->setAttribute('deleted_at', null);
        return $this->update(false) !== false;
    }
}
