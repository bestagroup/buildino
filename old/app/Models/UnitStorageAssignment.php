<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UnitStorageAssignment extends Model
{
    use HasFactory;

    protected $table = 'unit_storage_assignments';

    protected $fillable = [
        'unit_id',
        'storage_unit_id',
        'starts_at',
        'ends_at',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'date',
            'ends_at' => 'date',
        ];
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }

    public function storageUnit(): BelongsTo
    {
        return $this->belongsTo(StorageUnit::class, 'storage_unit_id');
    }
}
