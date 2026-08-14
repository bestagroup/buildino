<?php

namespace App\Models;

use App\Enums\SettingValueType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class SystemSetting extends Model
{
    use HasFactory;

    protected $table = 'system_settings';

    protected $fillable = [
        'scope',
        'key',
        'value',
        'type',
        'group',
        'description',
        'is_public',
        'scope_type',
        'scope_id',
    ];

    protected function casts(): array
    {
        return [
            'is_public' => 'boolean',
            'type' => SettingValueType::class,
        ];
    }

    public function scope(): MorphTo
    {
        return $this->morphTo();
    }
}
