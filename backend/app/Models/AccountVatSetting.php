<?php

declare(strict_types=1);

namespace HiEvents\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $account_id
 * @property bool $vat_registered
 * @property string|null $vat_number
 * @property bool $vat_validated
 * @property string $vat_validation_status
 * @property string|null $vat_validation_error
 * @property int $vat_validation_attempts
 * @property Carbon|null $vat_validation_date
 * @property string|null $business_name
 * @property string|null $business_address
 * @property string|null $vat_country_code
 * @property string|null $invoice_number_format
 * @property string|null $invoice_prefix
 * @property string|null $invoice_suffix
 * @property int $invoice_start_number
 * @property string|null $confirmation_prefix
 * @property int $confirmation_start_number
 */
class AccountVatSetting extends BaseModel
{
    use SoftDeletes;
    use HasFactory;

    protected function getFillableFields(): array
    {
        return [
            'account_id',
            'vat_registered',
            'vat_number',
            'vat_validated',
            'vat_validation_status',
            'vat_validation_error',
            'vat_validation_attempts',
            'vat_validation_date',
            'business_name',
            'business_address',
            'vat_country_code',
            'invoice_number_format',
            'invoice_prefix',
            'invoice_suffix',
            'invoice_start_number',
            'confirmation_prefix',
            'confirmation_start_number',
        ];
    }

    protected function getCastMap(): array
    {
        return [
            'vat_registered' => 'boolean',
            'vat_validated' => 'boolean',
            'vat_validation_attempts' => 'integer',
            'vat_validation_date' => 'datetime',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
