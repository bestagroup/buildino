<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BuildingStaff extends Model
{
    protected $table = 'building_staff';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['services' => 'array'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(UserRoleAssignment::class, 'assignment_id');
    }
}
