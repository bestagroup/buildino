<?php

namespace App\Models;

use App\Enums\ServiceRequestQuoteStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ServiceRequestQuote extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'service_request_id',
        'provider_user_id',
        'amount',
        'commission_rate_bps',
        'commission_amount',
        'provider_amount',
        'status',
        'notes',
        'valid_until',
        'accepted_by',
        'accepted_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'commission_rate_bps' => 'integer',
            'commission_amount' => 'integer',
            'provider_amount' => 'integer',
            'status' => ServiceRequestQuoteStatus::class,
            'valid_until' => 'datetime',
            'accepted_at' => 'datetime',
        ];
    }

    public function serviceRequest(): BelongsTo
    {
        return $this->belongsTo(ServiceRequest::class);
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'provider_user_id'
        );
    }

    public function acceptedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'accepted_by'
        );
    }

    public function walletPayment(): HasOne
    {
        return $this->hasOne(
            ServiceRequestWalletPayment::class,
            'service_request_quote_id'
        );
    }
}
