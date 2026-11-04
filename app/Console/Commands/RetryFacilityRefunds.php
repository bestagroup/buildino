<?php

namespace App\Console\Commands;

use App\Enums\RefundStatus;
use App\Models\ReservationCancellation;
use App\Services\Facility\FacilityWalletPaymentService;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

final class RetryFacilityRefunds extends Command
{
    protected $signature = 'facilities:refund-pending {--cancellation= : Retry a specific cancellation ID}';

    protected $description = 'Retry recorded pending facility wallet refunds without duplicating transfers';

    public function handle(FacilityWalletPaymentService $payments): int
    {
        $pending = 0;
        ReservationCancellation::query()
            ->where('refund_status', RefundStatus::Pending->value)
            ->whereNull('refund_wallet_transfer_id')->where('refund_amount', '>', 0)
            ->when($this->option('cancellation'), fn ($query) => $query->whereKey((int) $this->option('cancellation')))
            ->chunkById(100, function ($cancellations) use ($payments, &$pending): void {
                foreach ($cancellations as $cancellation) {
                    try {
                        $payments->refund($cancellation);
                    } catch (ValidationException) {
                        $pending++;
                        $this->warn("Refund {$cancellation->id} remains pending; check wallet balance and status.");
                    }
                }
            });

        return $pending > 0 ? self::FAILURE : self::SUCCESS;
    }
}
