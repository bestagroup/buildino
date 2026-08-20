<?php

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\PaymentReceipt;
use App\Services\Reports\Export\PdfReportWriter;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class PaymentReceiptService
{
    public function __construct(
        private readonly PdfReportWriter $pdf
    ) {}

    public function generate(Payment $payment): array
    {
        if ($payment->status !== PaymentStatus::Paid) {
            throw ValidationException::withMessages([
                'payment' => 'A receipt is available only after the payment is verified.',
            ]);
        }

        $receipt = DB::transaction(
            fn (): PaymentReceipt => PaymentReceipt::query()->firstOrCreate(
                ['payment_id' => $payment->getKey()],
                [
                    'receipt_number' => sprintf(
                        'RCP-%s',
                        $payment->payment_number
                    ),
                    'file_id' => null,
                ]
            ),
            3
        );

        $payment->loadMissing([
            'building:id,title',
            'payerUser:id,first_name,last_name,mobile',
            'paymentAllocations',
            'paymentTransactions',
        ]);

        $transaction = $payment->paymentTransactions
            ->sortByDesc('id')
            ->first();

        $payload = $this->pdf->write(
            'Buildino Payment Receipt',
            [
                'receipt_number' => $receipt->receipt_number,
                'payment_number' => $payment->payment_number,
                'building' => $payment->building?->title,
                'payer' => trim(
                    ($payment->payerUser?->first_name ?? '')
                    .' '
                    .($payment->payerUser?->last_name ?? '')
                ) ?: $payment->payerUser?->mobile,
                'amount' => (int) $payment->amount,
                'currency' => $payment->currency,
                'method' => $payment->method->value,
                'status' => $payment->status->value,
                'paid_at' => $payment->paid_at?->toISOString(),
                'verified_at' => $payment->verified_at?->toISOString(),
                'gateway' => $transaction?->gateway,
                'gateway_tracking_code' => $transaction?->tracking_code,
                'gateway_reference_number' => $transaction?->reference_number,
                'allocations' => $payment->paymentAllocations
                    ->map(fn ($allocation): array => [
                        'type' => $allocation->payable_type,
                        'id' => (int) $allocation->payable_id,
                        'amount' => (int) $allocation->amount,
                    ])
                    ->values()
                    ->all(),
                'issued_at' => $receipt->created_at?->toISOString(),
            ]
        );

        return [$receipt, $payload];
    }
}
