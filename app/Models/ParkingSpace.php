<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ParkingSpace extends Model
{
    use HasFactory;

    protected $fillable = [
        'unit_id',
        'parking_number',
        'title',
        'type',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
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

    public function scopePrivate($query)
    {
        return $query->where('type', 'private');
    }

    public function scopeShared($query)
    {
        return $query->where('type', 'shared');
    }

    public function scopeGuest($query)
    {
        return $query->where('type', 'guest');
    }
}
