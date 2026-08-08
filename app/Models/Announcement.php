<?php

namespace App\Models;

use App\Enums\AnnouncementType;
use App\Enums\AnnouncementPriority;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Announcement extends Model
{
    use HasFactory;

    protected $table = 'announcements';

    protected $fillable = [
        'created_by',
        'title',
        'content',
        'type',
        'priority',
        'starts_at',
        'expires_at',
        'published_at',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
            'published_at' => 'datetime',
            'is_active' => 'boolean',
            'type' => AnnouncementType::class,
            'priority' => AnnouncementPriority::class,
        ];
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function announcementTargets(): HasMany
    {
        return $this->hasMany(AnnouncementTarget::class, 'announcement_id');
    }

    public function announcementReceipts(): HasMany
    {
        return $this->hasMany(AnnouncementReceipt::class, 'announcement_id');
    }
}
