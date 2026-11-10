<?php

namespace app\models;

use app\components\BaseModel;

class AccountMove extends BaseModel
{
    public static function tableName(): string
    {
        return 'accounts_account_moves';
    }

    public static function isCompanyScoped(): bool
    {
        return true;
    }

    public function getPartner()
    {
        return $this->hasOne(Partner::class, ['id' => 'partner_id']);
    }

    public function getCommercialPartner()
    {
        return $this->hasOne(Partner::class, ['id' => 'commercial_partner_id']);
    }

    public function getPartnerShipping()
    {
        return $this->hasOne(Partner::class, ['id' => 'partner_shipping_id']);
    }

    public function getCurrency()
    {
        return $this->hasOne(Currency::class, ['id' => 'currency_id']);
    }

    public function getJournal()
    {
        return $this->hasOne(Journal::class, ['id' => 'journal_id']);
    }

    public function getCompany()
    {
        return $this->hasOne(Company::class, ['id' => 'company_id']);
    }

    public function getCreator()
    {
        return $this->hasOne(User::class, ['id' => 'creator_id']);
    }

    public function getPaymentTerm()
    {
        return $this->hasOne(PaymentTerm::class, ['id' => 'invoice_payment_term_id']);
    }

    public static function apiFields(): array
    {
        return [
            'id', 'sort', 'journal_id', 'company_id', 'campaign_id', 'invoice_payment_term_id',
            'partner_id', 'commercial_partner_id', 'partner_shipping_id', 'partner_bank_id',
            'fiscal_position_id', 'currency_id', 'reversed_entry_id', 'invoice_user_id',
            'invoice_incoterm_id', 'invoice_cash_rounding_id', 'preferred_payment_method_line_id',
            'creator_id', 'sequence_prefix', 'access_token', 'name', 'reference', 'state', 'move_type',
            'auto_post', 'payment_reference', 'payment_state', 'invoice_source_email',
            'invoice_partner_display_name', 'invoice_origin', 'incoterm_location', 'date',
            'invoice_date', 'invoice_date_due', 'delivery_date', 'narration', 'invoice_currency_rate',
            'amount_untaxed', 'amount_tax', 'amount_total', 'amount_residual', 'amount_untaxed_signed',
            'amount_tax_signed', 'amount_total_signed', 'amount_total_in_currency_signed',
            'amount_residual_signed', 'quick_edit_total_amount', 'is_storno', 'always_tax_exigible',
            'checked', 'posted_before', 'is_move_sent', 'source_id', 'medium_id', 'created_at', 'updated_at',
        ];
    }

    public static function apiCasts(): array
    {
        return [
            'sort' => 'int',
            'auto_post' => 'bool',
            'invoice_currency_rate' => 'float',
            'amount_untaxed' => 'float',
            'amount_tax' => 'float',
            'amount_total' => 'float',
            'amount_residual' => 'float',
            'amount_untaxed_signed' => 'float',
            'amount_tax_signed' => 'float',
            'amount_total_signed' => 'float',
            'amount_total_in_currency_signed' => 'float',
            'amount_residual_signed' => 'float',
            'is_storno' => 'bool',
            'always_tax_exigible' => 'bool',
            'checked' => 'bool',
            'posted_before' => 'bool',
            'is_move_sent' => 'bool',
            'date' => 'iso',
            'invoice_date' => 'iso',
            'invoice_date_due' => 'iso',
            'delivery_date' => 'iso',
            'created_at' => 'iso',
            'updated_at' => 'iso',
        ];
    }

    public static function apiRelations(): array
    {
        return [
            'partner' => 'partner',
            'commercial_partner' => 'commercialPartner',
            'partner_shipping' => 'partnerShipping',
            'currency' => 'currency',
            'journal' => 'journal',
            'company' => 'company',
            'creator' => 'creator',
            'payment_term' => 'paymentTerm',
        ];
    }

    public static function apiRules(): array
    {
        return ['name' => ['string']];
    }
}
