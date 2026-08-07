<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Building extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'complex_id',
        'code',
        'title',
        'building_number',
        'floors_count',
        'units_count',
        'parking_count',
        'storage_count',
        'construction_year',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'floors_count' => 'integer',
            'units_count' => 'integer',
            'parking_count' => 'integer',
            'storage_count' => 'integer',
            'construction_year' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function complex(): BelongsTo
    {
        return $this->belongsTo(Complex::class);
    }

    public function blocks(): HasMany
    {
        return $this->hasMany(Block::class);
    }

    public function emergencyContacts(): HasMany
    {
        return $this->hasMany(BuildingEmergencyContact::class);
    }

    public function rules(): HasMany
    {
        return $this->hasMany(BuildingRule::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(BuildingDocument::class);
    }

    public function activeBlocks(): HasMany
    {
        return $this->hasMany(Block::class)
            ->where('is_active', true);
    }

    public function activeRules(): HasMany
    {
        return $this->hasMany(BuildingRule::class)
            ->where('is_active', true);
    }

    public function activeEmergencyContacts(): HasMany
    {
        return $this->hasMany(BuildingEmergencyContact::class)
            ->orderBy('sort_order');
    }
}
