<?php

namespace App\Console\Commands;

use App\Enums\PaymentGatewayEventStatus;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\PaymentGatewayEvent;
use App\Models\PaymentTransaction;
use Illuminate\Console\Command;

class AuditPaymentGateways extends Command
{
    protected $signature = 'payments:gateway-audit
        {--stale-minutes=30 : Processing payment age considered stale}';

    protected $description =
        'Audit external payment gateway processing, event failures, and Payment/Transaction consistency';

    public function handle(): int
    {
        $staleMinutes = max(
            1,
            (int) $this->option(
                'stale-minutes'
            )
        );

        $staleBefore = now()
            ->subMinutes(
                $staleMinutes
            );

        $staleProcessing = Payment::query()
            ->where(
                'status',
                PaymentStatus::Processing->value
            )
            ->where(
                'updated_at',
                '<=',
                $staleBefore
            )
            ->count();

        $failedEvents =
            PaymentGatewayEvent::query()
                ->where(
                    'status',
                    PaymentGatewayEventStatus::Failed->value
                )
                ->count();

        $rejectedEvents =
            PaymentGatewayEvent::query()
                ->where(
                    'status',
                    PaymentGatewayEventStatus::Rejected->value
                )
                ->count();

        $paidWithUnverifiedGatewayTx =
            PaymentTransaction::query()
                ->whereNotNull('gateway')
                ->whereNotNull('authority')
                ->whereNull('verified_at')
                ->whereHas(
                    'payment',
                    fn ($query) =>
                        $query->where(
                            'status',
                            PaymentStatus::Paid->value
                        )
                )
                ->count();

        $verifiedTxWithUnpaidPayment =
            PaymentTransaction::query()
                ->whereNotNull(
                    'verified_at'
                )
                ->whereHas(
                    'payment',
                    fn ($query) =>
                        $query->where(
                            'status',
                            '!=',
                            PaymentStatus::Paid->value
                        )
                )
                ->count();

        $paidWithoutVerificationTime =
            Payment::query()
                ->where(
                    'status',
                    PaymentStatus::Paid->value
                )
                ->whereNull(
                    'verified_at'
                )
                ->count();

        $this->table(
            ['Check', 'Count'],
            [
                [
                    "Processing older than {$staleMinutes} minutes",
                    $staleProcessing,
                ],
                [
                    'Failed gateway events',
                    $failedEvents,
                ],
                [
                    'Rejected gateway/security events',
                    $rejectedEvents,
                ],
                [
                    'Paid payment with unresolved gateway transaction',
                    $paidWithUnverifiedGatewayTx,
                ],
                [
                    'Verified gateway transaction with unpaid payment',
                    $verifiedTxWithUnpaidPayment,
                ],
                [
                    'Paid payment without verified_at',
                    $paidWithoutVerificationTime,
                ],
            ]
        );

        /*
         * Rejected events may simply represent blocked attacks/replays and
         * are therefore reported but do not make the health check fail.
         */
        $criticalIssues =
            $staleProcessing
            + $failedEvents
            + $paidWithUnverifiedGatewayTx
            + $verifiedTxWithUnpaidPayment
            + $paidWithoutVerificationTime;

        return $criticalIssues > 0
            ? self::FAILURE
            : self::SUCCESS;
    }
}
