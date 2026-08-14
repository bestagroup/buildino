<?php

namespace App\Models;

use App\Enums\ServiceRequestPayerSource;
use App\Enums\ServiceRequestWalletPaymentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceRequestWalletPayment extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'service_request_id',
        'service_request_quote_id',
        'source_wallet_id',
        'provider_wallet_id',
        'platform_wallet_id',
        'payer_source',
        'amount',
        'provider_amount',
        'commission_amount',
        'status',
        'provider_transfer_id',
        'commission_transfer_id',
        'locked_at',
        'settled_at',
        'released_at',
    ];

    protected function casts(): array
    {
        return [
            'payer_source' => ServiceRequestPayerSource::class,
            'amount' => 'integer',
            'provider_amount' => 'integer',
            'commission_amount' => 'integer',
            'status' => ServiceRequestWalletPaymentStatus::class,
            'locked_at' => 'datetime',
            'settled_at' => 'datetime',
            'released_at' => 'datetime',
        ];
    }

    public function serviceRequest(): BelongsTo
    {
        return $this->belongsTo(ServiceRequest::class);
    }

    public function quote(): BelongsTo
    {
        return $this->belongsTo(
            ServiceRequestQuote::class,
            'service_request_quote_id'
        );
    }

    public function sourceWallet(): BelongsTo
    {
        return $this->belongsTo(
            Wallet::class,
            'source_wallet_id'
        );
    }

    public function providerWallet(): BelongsTo
    {
        return $this->belongsTo(
            Wallet::class,
            'provider_wallet_id'
        );
    }

    public function platformWallet(): BelongsTo
    {
        return $this->belongsTo(
            Wallet::class,
            'platform_wallet_id'
        );
    }

    public function providerTransfer(): BelongsTo
    {
        return $this->belongsTo(
            WalletTransfer::class,
            'provider_transfer_id'
        );
    }

    public function commissionTransfer(): BelongsTo
    {
        return $this->belongsTo(
            WalletTransfer::class,
            'commission_transfer_id'
        );
    }
}
