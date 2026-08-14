<?php

namespace App\Console\Commands;

use App\Models\FacilityReservation;
use Illuminate\Console\Command;

class ExpireFacilityReservations extends Command
{
    protected $signature = 'domain:expire-facility-reservations';
    protected $description = 'Expire temporary facility reservations after their expiration time.';

    public function handle(): int
    {
        FacilityReservation::query()
            ->whereIn('status', ['pending', 'payment_pending'])
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->update(['status' => 'expired']);

        return self::SUCCESS;
    }
}
