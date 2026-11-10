<?php

namespace app\models;

use Yii;
use app\components\BaseModel;

/**
 * Pesan chatter (polymorphic: messageable_type/messageable_id).
 *
 * Sumber (plugin chatter) tidak mengekspor REST API dan tidak punya permission Spatie,
 * jadi endpoint `chatter/messages` adalah definisi project ini dan hanya butuh autentikasi.
 */
class ChatterMessage extends BaseModel
{
    public static function tableName(): string
    {
        return 'chatter_messages';
    }

    public function beforeSave($insert)
    {
        if (!parent::beforeSave($insert)) {
            return false;
        }

        $this->is_read ??= 0;
        $this->is_internal ??= 0;

        if ($insert && !$this->causer_id) {
            $userId = Yii::$app->access?->currentUserId();
            if ($userId) {
                $this->causer_id = $userId;
                $this->causer_type ??= \app\components\Access::USER_TYPE;
            }
        }

        return true;
    }

    public function getCompany()
    {
        return $this->hasOne(Company::class, ['id' => 'company_id']);
    }

    public function getAssignedTo()
    {
        return $this->hasOne(User::class, ['id' => 'assigned_to']);
    }

    public function getCauser()
    {
        return $this->hasOne(User::class, ['id' => 'causer_id']);
    }

    public function getAttachments()
    {
        return $this->hasMany(ChatterAttachment::class, ['message_id' => 'id']);
    }

    public static function apiFields(): array
    {
        return [
            'id', 'company_id', 'activity_type_id', 'assigned_to', 'messageable_type',
            'messageable_id', 'type', 'name', 'subject', 'body', 'summary', 'is_internal',
            'is_read', 'date_deadline', 'pinned_at', 'log_name', 'causer_type', 'causer_id',
            'event', 'properties', 'created_at', 'updated_at',
        ];
    }

    public static function apiCasts(): array
    {
        return [
            'is_internal' => 'bool',
            'is_read' => 'bool',
            'properties' => 'json',
            'date_deadline' => 'iso',
            'pinned_at' => 'iso',
            'created_at' => 'iso',
            'updated_at' => 'iso',
        ];
    }

    public static function apiRelations(): array
    {
        return [
            'company' => 'company',
            'assignedTo' => 'assignedTo',
            'causer' => 'causer',
            'attachments' => 'attachments',
        ];
    }

    public static function apiRules(): array
    {
        return [
            'messageable_type' => ['required', 'string', 'max:255'],
            'messageable_id' => ['required', 'integer'],
            'type' => ['string', 'max:32'],
            'name' => ['string', 'max:255'],
            'subject' => ['string', 'max:255'],
            'body' => ['string'],
            'summary' => ['string'],
            'log_name' => ['string', 'max:255'],
            'event' => ['string', 'max:255'],
            'is_internal' => ['boolean'],
            'is_read' => ['boolean'],
            'date_deadline' => ['date'],
            'pinned_at' => ['date'],
            'assigned_to' => ['integer', 'exists:users'],
            'activity_type_id' => ['integer'],
            'company_id' => ['integer', 'exists:companies', 'company_allowed'],
        ];
    }
}
