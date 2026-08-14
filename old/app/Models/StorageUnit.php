<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StorageUnit extends Model
{
    use HasFactory;

    protected $table = 'storage_units';

    protected $fillable = [
        'building_id',
        'storage_number',
        'area',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'area' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function building(): BelongsTo
    {
        return $this->belongsTo(Building::class, 'building_id');
    }

    public function unitStorageAssignments(): HasMany
    {
        return $this->hasMany(UnitStorageAssignment::class, 'storage_unit_id');
    }

    public function units(): BelongsToMany
    {
        return $this->belongsToMany(Unit::class, 'unit_storage_assignments')
            ->withPivot(['starts_at', 'ends_at'])
            ->withTimestamps();
    }
}
