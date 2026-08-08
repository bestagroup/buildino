<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class FileRelation extends Model
{
    use HasFactory;

    protected $table = 'file_relations';

    protected $fillable = [
        'file_id',
        'related',
        'purpose',
        'related_type',
        'related_id',
    ];

    public function file(): BelongsTo
    {
        return $this->belongsTo(File::class, 'file_id');
    }

    public function related(): MorphTo
    {
        return $this->morphTo();
    }
}
