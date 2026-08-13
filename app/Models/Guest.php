<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Guest extends Model
{
    use HasFactory;

    protected $table = 'guests';

    protected $fillable = [
        'first_name',
        'last_name',
        'mobile',
        'national_code',
        'vehicle_plate',
    ];

    public function guestVisits(): HasMany
    {
        return $this->hasMany(
            GuestVisit::class,
            'guest_id'
        );
    }
}
