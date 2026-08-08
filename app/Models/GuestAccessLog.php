<?php

namespace App\Models;

use App\Enums\GuestAccessAction;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GuestAccessLog extends Model
{
    use HasFactory;

    protected $table = 'guest_access_logs';

    protected $fillable = [
        'guest_visit_id',
        'action',
        'occurred_at',
        'gate',
        'entry_method',
        'verified_by',
        'vehicle_plate',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'action' => GuestAccessAction::class,
        ];
    }

    public function guestVisit(): BelongsTo
    {
        return $this->belongsTo(GuestVisit::class, 'guest_visit_id');
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
