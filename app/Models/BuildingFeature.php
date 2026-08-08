<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BuildingFeature extends Model
{
    use HasFactory;

    protected $table = 'building_features';

    protected $fillable = [
        'building_id',
        'feature_id',
        'value',
        'is_enabled',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'array',
            'is_enabled' => 'boolean',
        ];
    }

    public function building(): BelongsTo
    {
        return $this->belongsTo(Building::class, 'building_id');
    }

    public function feature(): BelongsTo
    {
        return $this->belongsTo(Feature::class, 'feature_id');
    }
}
