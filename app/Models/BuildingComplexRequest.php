<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BuildingComplexRequest extends Model
{
    protected $fillable = ['building_id', 'complex_id', 'requested_by', 'status', 'reviewed_by', 'reviewed_at'];

    protected function casts(): array
    {
        return ['reviewed_at' => 'datetime'];
    }

    public function building(): BelongsTo
    {
        return $this->belongsTo(Building::class);
    }

    public function complex(): BelongsTo
    {
        return $this->belongsTo(Complex::class);
    }
}
