<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class PlatformWalletAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'code',
        'title',
        'currency',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function wallets(): MorphMany
    {
        return $this->morphMany(
            Wallet::class,
            'owner'
        );
    }
}
