<?php

namespace App\Models;

use App\Enums\FileVisibility;
use App\Enums\FileScanStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class File extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'files';

    protected $fillable = [
        'uuid',
        'uploaded_by',
        'disk',
        'visibility',
        'path',
        'stored_name',
        'original_name',
        'extension',
        'mime_type',
        'size',
        'checksum',
        'category',
        'is_confidential',
        'scan_status',
        'scanned_at',
        'expires_at',
        'metadata',
    ];

    protected $hidden = [
        'id',
        'uploaded_by',
        'disk',
        'path',
        'stored_name',
        'checksum',
        'metadata',
        'deleted_at',
    ];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'is_confidential' => 'boolean',
            'scanned_at' => 'datetime',
            'expires_at' => 'datetime',
            'metadata' => 'array',
            'visibility' => FileVisibility::class,
            'scan_status' => FileScanStatus::class,
        ];
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function fileRelations(): HasMany
    {
        return $this->hasMany(FileRelation::class, 'file_id');
    }

    public function fileDownloads(): HasMany
    {
        return $this->hasMany(FileDownload::class, 'file_id');
    }

    public function generatedReports(): HasMany
    {
        return $this->hasMany(GeneratedReport::class, 'file_id');
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
