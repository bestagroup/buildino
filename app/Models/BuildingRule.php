<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BuildingRule extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'building_id',
        'title',
        'content',
        'is_active',
        'effective_from',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'effective_from' => 'date',
        ];
    }

    public function building(): BelongsTo
    {
        return $this->belongsTo(Building::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeEffective($query)
    {
        return $query->where(function ($query) {
            $query->whereNull('effective_from')
                ->orWhere(
                    'effective_from',
                    '<=',
                    now()->toDateString()
                );
        });
    }
}
