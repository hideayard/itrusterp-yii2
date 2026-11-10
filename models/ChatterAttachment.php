<?php

namespace app\models;

use app\components\BaseModel;

/**
 * Lampiran chatter.
 *
 * Deviasi: upload multipart belum diport — API hanya menyimpan metadata
 * (file_path, name, original_file_name, mime_type, file_size).
 */
class ChatterAttachment extends BaseModel
{
    public static function tableName(): string
    {
        return 'chatter_attachments';
    }

    public function getMessage()
    {
        return $this->hasOne(ChatterMessage::class, ['id' => 'message_id']);
    }

    public function getCompany()
    {
        return $this->hasOne(Company::class, ['id' => 'company_id']);
    }

    public function getCreator()
    {
        return $this->hasOne(User::class, ['id' => 'creator_id']);
    }

    public static function apiFields(): array
    {
        return [
            'id', 'name', 'file_size', 'messageable_type', 'messageable_id', 'file_path',
            'original_file_name', 'mime_type', 'company_id', 'creator_id', 'message_id',
            'created_at', 'updated_at',
        ];
    }

    public static function apiCasts(): array
    {
        return ['created_at' => 'iso', 'updated_at' => 'iso'];
    }

    public static function apiRelations(): array
    {
        return ['message' => 'message', 'company' => 'company', 'creator' => 'creator'];
    }

    public static function apiRules(): array
    {
        return [
            'messageable_type' => ['required', 'string', 'max:255'],
            'messageable_id' => ['required', 'integer'],
            'message_id' => ['integer', 'exists:chatter_messages'],
            'name' => ['string', 'max:255'],
            'file_path' => ['string', 'max:255'],
            'original_file_name' => ['string', 'max:255'],
            'mime_type' => ['string', 'max:255'],
            'file_size' => ['string', 'max:64'],
            'company_id' => ['integer', 'exists:companies', 'company_allowed'],
        ];
    }
}
