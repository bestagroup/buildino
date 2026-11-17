<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ExpensePeriodAllocation extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['amount' => 'integer', 'sequence' => 'integer'];
    }

    public function chargePeriod(): BelongsTo
    {
        return $this->belongsTo(ChargePeriod::class);
    }
}
