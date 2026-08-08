<?php

namespace App\Models;

use App\Enums\FeatureValueType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Feature extends Model
{
    use HasFactory;

    protected $table = 'features';

    protected $fillable = [
        'code',
        'title',
        'description',
        'value_type',
    ];

    public function planFeatures(): HasMany
    {
        return $this->hasMany(PlanFeature::class, 'feature_id');
    }

    public function buildingFeatures(): HasMany
    {
        return $this->hasMany(BuildingFeature::class, 'feature_id');
    }

    public function plans(): BelongsToMany
    {
        return $this->belongsToMany(Plan::class, 'plan_features')
            ->withPivot(['value'])
            ->withTimestamps();
    }
    protected function casts(): array
    {
        return [
            'value_type' => FeatureValueType::class,
        ];
    }

}
