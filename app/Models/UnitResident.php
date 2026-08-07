<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class UnitResident extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'unit_id',
        'user_id',
        'resident_type',
        'ownership_percentage',
        'start_date',
        'end_date',
        'is_primary',
        'is_active',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'ownership_percentage' => 'integer',
            'start_date' => 'date',
            'end_date' => 'date',
            'is_primary' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function histories(): HasMany
    {
        return $this->hasMany(ResidentHistory::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopePrimary($query)
    {
        return $query->where('is_primary', true);
    }

    public function scopeOwners($query)
    {
        return $query->where('resident_type', 'owner');
    }

    public function scopeTenants($query)
    {
        return $query->where('resident_type', 'tenant');
    }

    public function scopeFamilyMembers($query)
    {
        return $query->where('resident_type', 'family_member');
    }

    public function scopeRepresentatives($query)
    {
        return $query->where('resident_type', 'representative');
    }

    public function isOwner(): bool
    {
        return $this->resident_type === 'owner';
    }

    public function isTenant(): bool
    {
        return $this->resident_type === 'tenant';
    }

    public function isPrimary(): bool
    {
        return $this->is_primary === true;
    }

    public function isActive(): bool
    {
        return $this->is_active === true;
    }
}
