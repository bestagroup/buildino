<?php

namespace App\Console\Commands;

use App\Enums\GuestVisitStatus;
use App\Models\GuestVisit;
use Illuminate\Console\Command;

class ExpireGuestVisits extends Command
{
    protected $signature = 'domain:expire-guest-visits';

    protected $description = 'Expire invited guest visits after their expected exit time.';

    public function handle(): int
    {
        $count = GuestVisit::query()
            ->where(
                'status',
                GuestVisitStatus::Invited->value
            )
            ->whereNotNull(
                'expected_exit_at'
            )
            ->where(
                'expected_exit_at',
                '<=',
                now()
            )
            ->update([
                'status' => GuestVisitStatus::Expired->value,
            ]);

        $this->info(
            sprintf(
                '%d guest visit(s) expired.',
                $count
            )
        );

        return self::SUCCESS;
    }
}
