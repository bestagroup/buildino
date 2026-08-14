<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReportDefinition extends Model
{
    use HasFactory;

    protected $table = 'report_definitions';

    protected $fillable = [
        'title',
        'code',
        'module',
        'configuration',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'configuration' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function generatedReports(): HasMany
    {
        return $this->hasMany(GeneratedReport::class, 'report_definition_id');
    }
}
