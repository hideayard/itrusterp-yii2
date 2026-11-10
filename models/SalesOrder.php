<?php

namespace app\models;

use Yii;
use app\components\BaseModel;
use app\components\SequenceService;

class SalesOrder extends BaseModel
{
    public const STATE_DRAFT = 'draft';
    public const STATE_SENT = 'sent';
    public const STATE_SALE = 'sale';
    public const STATE_CANCEL = 'cancel';

    public static function tableName(): string
    {
        return 'sales_orders';
    }

    public static function isSoftDelete(): bool
    {
        return true;
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
            $this->date_order ??= date('Y-m-d');

            if ($this->partner_id) {
                $partner = Partner::findUnscoped()->where(['id' => $this->partner_id])->one();
                if ($partner) {
                    $this->partner_shipping_id ??= $partner->id;
                    $this->partner_invoice_id ??= $partner->id;
                }
            }

            if ($this->currency_id === null && $this->company_id) {
                $company = Company::findUnscoped()->where(['id' => $this->company_id])->one();
                $this->currency_id = $company?->currency_id;
            }
            if ($this->currency_id === null) {
                $this->currency_id = Currency::findUnscoped()
                    ->where(['active' => 1])
                    ->orderBy(['id' => SORT_ASC])
                    ->select('id')
                    ->scalar();
            }
        }

        if (!$this->name) {
            $companyId = $this->company_id !== null ? (int) $this->company_id : null;
            $this->name = SequenceService::next('sales.order', $companyId, [
                'name' => 'Sales Order',
                'prefix' => 'SO/',
                'padding' => 5,
                'initial_from' => [
                    'table' => 'sales_orders',
                    'column' => 'name',
                    'pattern' => '/SO\/(\d+)\s*$/',
                ],
            ]);
        }

        return true;
    }

    public function getPartner()
    {
        return $this->hasOne(Partner::class, ['id' => 'partner_id']);
    }

    public function getPartnerInvoice()
    {
        return $this->hasOne(Partner::class, ['id' => 'partner_invoice_id']);
    }

    public function getPartnerShipping()
    {
        return $this->hasOne(Partner::class, ['id' => 'partner_shipping_id']);
    }

    public function getUser()
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }

    public function getCreator()
    {
        return $this->hasOne(User::class, ['id' => 'creator_id']);
    }

    public function getTeam()
    {
        return $this->hasOne(SalesTeam::class, ['id' => 'team_id']);
    }

    public function getCompany()
    {
        return $this->hasOne(Company::class, ['id' => 'company_id']);
    }

    public function getCurrency()
    {
        return $this->hasOne(Currency::class, ['id' => 'currency_id']);
    }

    public function getPriceList()
    {
        return $this->hasOne(PriceList::class, ['id' => 'price_list_id']);
    }

    public function getLines()
    {
        return $this->hasMany(SalesOrderLine::class, ['order_id' => 'id'])
            ->orderBy(['sort' => SORT_ASC, 'id' => SORT_ASC]);
    }

    public function getTags()
    {
        return $this->hasMany(SalesTag::class, ['id' => 'tag_id'])
            ->viaTable('sales_order_tags', ['order_id' => 'id']);
    }

    public static function apiFields(): array
    {
        return [
            'id', 'name', 'state', 'invoice_status', 'client_order_ref', 'origin', 'reference',
            'date_order', 'validity_date', 'commitment_date', 'currency_rate',
            'amount_untaxed', 'amount_tax', 'amount_total', 'locked', 'require_signature',
            'require_payment', 'signed_by', 'signed_on', 'prepayment_percent', 'note', 'access_token',
            'partner_id', 'partner_invoice_id', 'partner_shipping_id', 'user_id', 'team_id',
            'company_id', 'currency_id', 'price_list_id', 'payment_term_id', 'fiscal_position_id',
            'journal_id', 'campaign_id', 'utm_source_id', 'medium_id', 'warehouse_id',
            'created_at', 'updated_at', 'deleted_at',
        ];
    }

    public static function apiCasts(): array
    {
        return [
            'date_order' => 'iso',
            'validity_date' => 'iso',
            'commitment_date' => 'iso',
            'signed_on' => 'iso',
            'currency_rate' => 'float',
            'amount_untaxed' => 'float',
            'amount_tax' => 'float',
            'amount_total' => 'float',
            'locked' => 'bool',
            'require_signature' => 'bool',
            'require_payment' => 'bool',
            'prepayment_percent' => 'float',
            'created_at' => 'iso',
            'updated_at' => 'iso',
            'deleted_at' => 'iso',
        ];
    }

    public static function apiRelations(): array
    {
        return [
            'partner' => 'partner',
            'partner_invoice' => 'partnerInvoice',
            'partner_shipping' => 'partnerShipping',
            'user' => 'user',
            'creator' => 'creator',
            'team' => 'team',
            'company' => 'company',
            'currency' => 'currency',
            'price_list' => 'priceList',
            'lines' => 'lines',
            'tags' => 'tags',
        ];
    }

    public static function apiRules(): array
    {
        return [
            'partner_id' => ['required', 'integer', 'exists:partners_partners'],
            'payment_term_id' => ['required', 'integer', 'exists:accounts_payment_terms'],
            'date_order' => ['required', 'date'],
            'validity_date' => ['date'],
            'commitment_date' => ['date'],
            'client_order_ref' => ['string', 'max:255'],
            'origin' => ['string', 'max:255'],
            'note' => ['string'],
            'user_id' => ['integer', 'exists:users'],
            'team_id' => ['integer', 'exists:sales_teams'],
            'company_id' => ['integer', 'exists:companies', 'company_allowed'],
            'currency_id' => ['integer', 'exists:currencies'],
            'price_list_id' => ['integer', 'exists:products_product_price_lists'],
            'campaign_id' => ['integer', 'exists:utm_campaigns'],
            'utm_source_id' => ['integer', 'exists:utm_sources'],
            'medium_id' => ['integer', 'exists:utm_mediums'],
            'warehouse_id' => ['integer', 'exists:inventories_warehouses'],
        ];
    }
}
