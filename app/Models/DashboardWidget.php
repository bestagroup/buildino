<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DashboardWidget extends Model
{
    use HasFactory;

    protected $table = 'dashboard_widgets';

    protected $fillable = [
        'title',
        'code',
        'type',
        'configuration',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'configuration' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function userDashboardWidgets(): HasMany
    {
        return $this->hasMany(UserDashboardWidget::class, 'dashboard_widget_id');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_dashboard_widgets')
            ->withPivot(['position', 'configuration'])
            ->withTimestamps();
    }
}
