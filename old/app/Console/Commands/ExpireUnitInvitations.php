<?php

namespace App\Console\Commands;

use App\Enums\InvitationStatus;
use App\Models\UnitInvitation;
use Illuminate\Console\Command;

class ExpireUnitInvitations extends Command
{
    protected $signature = 'domain:expire-unit-invitations';

    protected $description = 'Expire active unit invitations whose expiration date has passed.';

    public function handle(): int
    {
        $count = UnitInvitation::query()
            ->whereIn(
                'status',
                [
                    InvitationStatus::Pending->value,
                    InvitationStatus::Sent->value,
                ]
            )
            ->whereNotNull('expires_at')
            ->where(
                'expires_at',
                '<=',
                now()
            )
            ->update([
                'status' => InvitationStatus::Expired->value,
            ]);

        $this->info(
            sprintf(
                '%d unit invitation(s) expired.',
                $count
            )
        );

        return self::SUCCESS;
    }
}
