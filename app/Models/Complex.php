<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Complex extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'code',
        'title',
        'manager_name',
        'manager_mobile',
        'province',
        'city',
        'address',
        'latitude',
        'longitude',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'integer',
            'longitude' => 'integer',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function buildings(): HasMany
    {
        return $this->hasMany(Building::class);
    }

    public function activeBuildings(): HasMany
    {
        return $this->hasMany(Building::class)
            ->where('is_active', true);
    }
}
