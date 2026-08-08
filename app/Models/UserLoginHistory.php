<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UserLoginHistory extends Model
{
    use HasFactory;

    protected $table = 'user_login_histories';

    protected $fillable = [
        'user_id',
        'session_id',
        'device_id',
        'ip_address',
        'device',
        'browser',
        'platform',
        'is_successful',
        'failure_reason',
        'login_at',
        'logout_at',
    ];

    protected function casts(): array
    {
        return [
            'is_successful' => 'boolean',
            'login_at' => 'datetime',
            'logout_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
