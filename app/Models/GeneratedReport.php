<?php

namespace App\Models;

use App\Enums\ReportStatus;
use App\Enums\ReportFormat;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GeneratedReport extends Model
{
    use HasFactory;

    protected $table = 'generated_reports';

    protected $fillable = [
        'report_definition_id',
        'building_id',
        'generated_by',
        'file_id',
        'format',
        'status',
        'filters',
        'started_at',
        'completed_at',
        'failed_at',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'filters' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'failed_at' => 'datetime',
            'format' => ReportFormat::class,
            'status' => ReportStatus::class,
        ];
    }

    public function reportDefinition(): BelongsTo
    {
        return $this->belongsTo(ReportDefinition::class, 'report_definition_id');
    }

    public function building(): BelongsTo
    {
        return $this->belongsTo(Building::class, 'building_id');
    }

    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    public function file(): BelongsTo
    {
        return $this->belongsTo(File::class, 'file_id');
    }
}
