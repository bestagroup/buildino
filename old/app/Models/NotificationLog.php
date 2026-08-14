<?php

namespace App\Models;

use App\Enums\NotificationChannel;
use App\Enums\NotificationStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class NotificationLog extends Model
{
    use HasFactory;

    protected $table = 'notification_logs';

    protected $fillable = [
        'idempotency_key',
        'notifiable_type',
        'notifiable_id',
        'notification_type',
        'channel',
        'provider',
        'provider_message_id',
        'title',
        'message',
        'status',
        'attempts',
        'last_attempt_at',
        'sent_at',
        'delivered_at',
        'read_at',
        'failed_at',
        'failure_reason',
        'response',
    ];

    protected function casts(): array
    {
        return [
            'attempts' => 'integer',
            'last_attempt_at' => 'datetime',
            'sent_at' => 'datetime',
            'delivered_at' => 'datetime',
            'read_at' => 'datetime',
            'failed_at' => 'datetime',
            'response' => 'array',
            'channel' => NotificationChannel::class,
            'status' => NotificationStatus::class,
        ];
    }

    public function notifiable(): MorphTo
    {
        return $this->morphTo();
    }
}
