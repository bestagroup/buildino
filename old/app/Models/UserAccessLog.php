<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UserAccessLog extends Model
{
    use HasFactory;

    protected $table = 'user_access_logs';

    protected $fillable = [
        'request_id',
        'user_id',
        'module',
        'action',
        'method',
        'url',
        'route',
        'parameters',
        'response_status',
        'duration_ms',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'parameters' => 'array',
            'response_status' => 'integer',
            'duration_ms' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
