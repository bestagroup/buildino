<?php

namespace App\Console\Commands;

use App\Services\Loyalty\LoyaltyLedgerService;
use Illuminate\Console\Command;

class ExpireLoyaltyPoints extends Command
{
    protected $signature = 'loyalty:expire-points';

    protected $description = 'Expire unused loyalty points past their expiration time';

    public function handle(LoyaltyLedgerService $loyalty): int
    {
        $points = $loyalty->expireDue();
        $this->info("Expired {$points} loyalty point(s).");

        return self::SUCCESS;
    }
}
