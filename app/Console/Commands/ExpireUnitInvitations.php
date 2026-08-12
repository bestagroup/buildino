<?php

namespace App\Console\Commands;

use App\Models\UnitInvitation;
use Illuminate\Console\Command;

class ExpireUnitInvitations extends Command
{
    protected $signature = 'domain:expire-unit-invitations';
    protected $description = 'Expire pending unit invitations whose expiration date has passed.';

    public function handle(): int
    {
        UnitInvitation::query()
            ->where('status', 'pending')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->update(['status' => 'expired']);

        return self::SUCCESS;
    }
}
