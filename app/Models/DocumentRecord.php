<?php

namespace App\Models;

use App\Enums\DocumentType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class DocumentRecord extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'document_records';

    protected $fillable = [
        'title',
        'document_type',
        'document_number',
        'document_date',
        'expires_at',
        'description',
        'created_by',
        'documentable_type',
        'documentable_id',
    ];

    protected function casts(): array
    {
        return [
            'document_date' => 'date',
            'expires_at' => 'date',
            'document_type' => DocumentType::class,
        ];
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function documentable(): MorphTo
    {
        return $this->morphTo();
    }

    public function fileRelations(): MorphMany
    {
        return $this->morphMany(FileRelation::class, 'related');
    }
}
