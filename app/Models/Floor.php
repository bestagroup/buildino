<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Floor extends Model
{
    use HasFactory;

    protected $fillable = [
        'block_id',
        'floor_number',
        'title',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'floor_number' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function block(): BelongsTo
    {
        return $this->belongsTo(Block::class);
    }

    public function units(): HasMany
    {
        return $this->hasMany(Unit::class);
    }

    public function activeUnits(): HasMany
    {
        return $this->hasMany(Unit::class)
            ->where('is_active', true);
    }
}
