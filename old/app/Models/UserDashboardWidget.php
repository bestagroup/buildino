<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UserDashboardWidget extends Model
{
    use HasFactory;

    protected $table = 'user_dashboard_widgets';

    protected $fillable = [
        'user_id',
        'dashboard_widget_id',
        'position',
        'configuration',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'configuration' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function dashboardWidget(): BelongsTo
    {
        return $this->belongsTo(DashboardWidget::class, 'dashboard_widget_id');
    }
}
