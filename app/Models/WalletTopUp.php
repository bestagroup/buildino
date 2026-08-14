<?php

namespace App\Models;

use App\Enums\WalletTopUpStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class WalletTopUp extends Model
{
    use HasFactory;

    protected $table = 'wallet_topups';

    protected $fillable = [
        'uuid',
        'payment_id',
        'wallet_id',
        'target_type',
        'target_id',
        'amount',
        'currency',
        'status',
        'wallet_transfer_id',
        'credited_at',
        'retry_attempted_at',
        'retry_summary',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'status' => WalletTopUpStatus::class,
            'credited_at' => 'datetime',
            'retry_attempted_at' => 'datetime',
            'retry_summary' => 'array',
        ];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function transfer(): BelongsTo
    {
        return $this->belongsTo(
            WalletTransfer::class,
            'wallet_transfer_id'
        );
    }

    public function target(): MorphTo
    {
        return $this->morphTo();
    }

    public function targetKind(): string
    {
        $type = $this->target_type;

        if (
            in_array(
                $type,
                [
                    (new User())->getMorphClass(),
                    User::class,
                ],
                true
            )
        ) {
            return 'user_wallet';
        }

        return 'unit_wallet';
    }
}
