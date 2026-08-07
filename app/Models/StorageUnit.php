<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StorageUnit extends Model
{
    use HasFactory;

    protected $fillable = [
        'unit_id',
        'storage_number',
        'area',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'area' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
