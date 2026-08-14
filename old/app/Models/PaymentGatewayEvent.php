<?php

namespace App\Models;

use App\Enums\PaymentGatewayEventStatus;
use App\Enums\PaymentGatewayEventType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentGatewayEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'gateway',
        'event_key',
        'payment_transaction_id',
        'event_type',
        'authority',
        'payload_hash',
        'request_payload',
        'signature_valid',
        'source_ip',
        'user_agent',
        'status',
        'attempts',
        'error_message',
        'received_at',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'event_type' =>
                PaymentGatewayEventType::class,

            'signature_valid' =>
                'boolean',

            'status' =>
                PaymentGatewayEventStatus::class,

            'attempts' =>
                'integer',

            'request_payload' =>
                'array',

            'received_at' =>
                'datetime',

            'processed_at' =>
                'datetime',
        ];
    }

    public function paymentTransaction(): BelongsTo
    {
        return $this->belongsTo(
            PaymentTransaction::class
        );
    }
}
