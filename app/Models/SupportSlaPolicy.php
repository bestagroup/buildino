<?php

namespace App\Models;

use App\Enums\SupportPriority;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupportSlaPolicy extends Model
{
    use HasFactory;

    protected $table = 'support_sla_policies';

    protected $fillable = [
        'support_category_id',
        'priority',
        'first_response_minutes',
        'resolution_minutes',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'first_response_minutes' => 'integer',
            'resolution_minutes' => 'integer',
            'is_active' => 'boolean',
            'priority' => SupportPriority::class,
        ];
    }

    public function supportCategory(): BelongsTo
    {
        return $this->belongsTo(SupportCategory::class, 'support_category_id');
    }
}
