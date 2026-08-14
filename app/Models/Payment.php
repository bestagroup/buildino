<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use App\Enums\PaymentMethod;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Payment extends Model
{
    use HasFactory;

    protected $table = 'payments';

    protected $fillable = [
        'uuid',
        'building_id',
        'payer_user_id',
        'payment_number',
        'amount',
        'currency',
        'method',
        'status',
        'paid_at',
        'verified_at',
        'verified_by',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'paid_at' => 'datetime',
            'verified_at' => 'datetime',
            'method' => PaymentMethod::class,
            'status' => PaymentStatus::class,
        ];
    }

    public function building(): BelongsTo
    {
        return $this->belongsTo(Building::class, 'building_id');
    }

    public function payerUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'payer_user_id');
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function paymentAllocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class, 'payment_id');
    }

    public function paymentTransactions(): HasMany
    {
        return $this->hasMany(PaymentTransaction::class, 'payment_id');
    }

    public function paymentReceipts(): HasMany
    {
        return $this->hasMany(PaymentReceipt::class, 'payment_id');
    }


    public function walletTopUp(): HasOne
    {
        return $this->hasOne(
            WalletTopUp::class,
            'payment_id'
        );
    }

    public function reservationCancellations(): HasMany
    {
        return $this->hasMany(ReservationCancellation::class, 'refund_payment_id');
    }

    public function fileRelations(): MorphMany
    {
        return $this->morphMany(FileRelation::class, 'related');
    }
}
