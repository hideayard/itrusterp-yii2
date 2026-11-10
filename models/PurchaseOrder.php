<?php

namespace app\models;

use Yii;
use app\components\BaseModel;
use app\components\SequenceService;

class PurchaseOrder extends BaseModel
{
    public const STATE_DRAFT = 'draft';
    public const STATE_SENT = 'sent';
    public const STATE_TO_APPROVE = 'to_approve';
    public const STATE_PURCHASE = 'purchase';
    public const STATE_DONE = 'done';
    public const STATE_CANCELED = 'canceled';

    public static function tableName(): string
    {
        return 'purchases_orders';
    }

    public static function isCompanyScoped(): bool
    {
        return true;
    }

    public static function isOwnershipScoped(): bool
    {
        return true;
    }

    public static function ownershipSources(): array
    {
        return ['creator_id', 'user_id'];
    }

    public static function autoAssignCompany(): bool
    {
        return true;
    }

    public function beforeSave($insert)
    {
        if (!parent::beforeSave($insert)) {
            return false;
        }

        if ($insert) {
            $userId = Yii::$app->access->currentUserId();
            $this->creator_id ??= $userId;
            $this->user_id ??= $userId;
            $this->state ??= self::STATE_DRAFT;
            $this->calendar_start_at ??= $this->ordered_at;
            if (!$this->currency_rate) {
                $this->currency_rate = 1;
            }
        }

        if (!$this->name) {
            $companyId = $this->company_id !== null ? (int) $this->company_id : null;
            $this->name = SequenceService::next('purchases.order', $companyId, [
                'name' => 'Purchase Order',
                'prefix' => 'PO/',
                'padding' => 5,
                'initial_from' => [
                    'table' => 'purchases_orders',
                    'column' => 'name',
                    'pattern' => '/PO\/(\d+)\s*$/',
                ],
            ]);
        }

        return true;
    }

    public function getPartner()
    {
        return $this->hasOne(Partner::class, ['id' => 'partner_id']);
    }

    public function getCurrency()
    {
        return $this->hasOne(Currency::class, ['id' => 'currency_id']);
    }

    public function getUser()
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }

    public function getCompany()
    {
        return $this->hasOne(Company::class, ['id' => 'company_id']);
    }

    public function getCreator()
    {
        return $this->hasOne(User::class, ['id' => 'creator_id']);
    }

    public function getRequisition()
    {
        return $this->hasOne(PurchaseAgreement::class, ['id' => 'requisition_id'])->withTrashed();
    }

    public function getLines()
    {
        return $this->hasMany(PurchaseOrderLine::class, ['order_id' => 'id'])
            ->orderBy(['id' => SORT_ASC]);
    }

    public static function apiFields(): array
    {
        return [
            'id', 'name', 'description', 'priority', 'origin', 'partner_reference', 'state',
            'invoice_status', 'receipt_status', 'untaxed_amount', 'tax_amount', 'total_amount',
            'total_cc_amount', 'currency_rate', 'mail_reminder_confirmed', 'mail_reception_confirmed',
            'mail_reception_declined', 'invoice_count', 'ordered_at', 'approved_at', 'planned_at',
            'calendar_start_at', 'incoterm_location', 'effective_date', 'report_grids',
            'requisition_id', 'purchases_group_id', 'partner_id', 'currency_id', 'fiscal_position_id',
            'payment_term_id', 'incoterm_id', 'user_id', 'company_id', 'creator_id', 'operation_type_id',
            'created_at', 'updated_at',
        ];
    }

    public static function apiCasts(): array
    {
        return [
            'untaxed_amount' => 'float',
            'tax_amount' => 'float',
            'total_amount' => 'float',
            'total_cc_amount' => 'float',
            'currency_rate' => 'float',
            'invoice_count' => 'int',
            'mail_reminder_confirmed' => 'bool',
            'mail_reception_confirmed' => 'bool',
            'mail_reception_declined' => 'bool',
            'report_grids' => 'bool',
            'ordered_at' => 'iso',
            'approved_at' => 'iso',
            'planned_at' => 'iso',
            'calendar_start_at' => 'iso',
            'effective_date' => 'iso',
            'created_at' => 'iso',
            'updated_at' => 'iso',
        ];
    }

    public static function apiRelations(): array
    {
        return [
            'partner' => 'partner',
            'currency' => 'currency',
            'user' => 'user',
            'company' => 'company',
            'creator' => 'creator',
            'requisition' => 'requisition',
            'lines' => 'lines',
        ];
    }

    public static function apiRules(): array
    {
        return [
            'partner_id' => ['required', 'integer', 'exists:partners_partners'],
            'currency_id' => ['required', 'integer', 'exists:currencies'],
            'ordered_at' => ['required', 'date'],
            'company_id' => ['required', 'integer', 'exists:companies', 'company_allowed'],
            'partner_reference' => ['string', 'max:255'],
            'requisition_id' => ['integer', 'exists:purchases_requisitions'],
            'planned_at' => ['date'],
            'user_id' => ['integer', 'exists:users'],
            'payment_term_id' => ['integer', 'exists:accounts_payment_terms'],
            'incoterm_id' => ['integer', 'exists:accounts_incoterms'],
            'origin' => ['string', 'max:255'],
            'description' => ['string'],
            'priority' => ['integer'],
        ];
    }
}
