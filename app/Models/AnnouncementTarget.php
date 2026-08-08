<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AnnouncementTarget extends Model
{
    use HasFactory;

    protected $table = 'announcement_targets';

    protected $fillable = [
        'announcement_id',
        'target',
        'target_type',
        'target_id',
    ];

    public function announcement(): BelongsTo
    {
        return $this->belongsTo(Announcement::class, 'announcement_id');
    }

    public function target(): MorphTo
    {
        return $this->morphTo();
    }
}
