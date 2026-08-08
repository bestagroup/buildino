<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Complex extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'complexes';

    protected $fillable = [
        'code',
        'title',
        'province',
        'city',
        'address',
        'postal_code',
        'latitude',
        'longitude',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function buildings(): HasMany
    {
        return $this->hasMany(Building::class, 'complex_id');
    }
}
