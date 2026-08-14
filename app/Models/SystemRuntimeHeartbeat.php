<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemRuntimeHeartbeat extends Model
{
    protected $primaryKey = 'name';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'name',
        'last_seen_at',
        'host',
        'process_id',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'last_seen_at' => 'datetime',
            'process_id' => 'integer',
            'metadata' => 'array',
        ];
    }
}
