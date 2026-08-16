<?php

namespace App\Services\Web;

use App\Models\FacilityReservation;
use App\Models\GuestVisit;
use App\Models\ProviderPayoutRequest;
use App\Models\ServiceRequest;
use App\Models\SupportTicket;
use App\Models\UnitInvoice;
use App\Models\User;
use App\Models\WalletEntry;
use Illuminate\Http\Response;

final class PortalOperationDetailService
{
    public function __construct(
        private readonly PortalAccessService $access,
        private readonly WebDataTablePresenter $presenter
    ) {
    }

    public function resident(
        User $user,
        string $resource,
        int $id
    ): array {
        $unitIds =
            $this->access
                ->residentUnits(
                    $user
                )
                ->pluck('id')
                ->all();

        return match ($resource) {
            'invoices' =>
                $this->residentInvoice(
                    $unitIds,
                    $id
                ),

            'reservations' =>
                $this->residentReservation(
                    $user,
                    $unitIds,
                    $id
                ),

            'guests' =>
                $this->residentGuest(
                    $unitIds,
                    $id
                ),

            'services' =>
                $this->residentService(
                    $user,
                    $unitIds,
                    $id
                ),

            'support' =>
                $this->residentSupport(
                    $user,
                    $unitIds,
                    $id
                ),

            'wallet' =>
                $this->residentWalletEntry(
                    $user,
                    $unitIds,
                    $id
                ),

            default =>
                abort(
                    Response::HTTP_NOT_FOUND
                ),
        };
    }

    public function provider(
        User $user,
        string $resource,
        int $id
    ): array {
        return match ($resource) {
            'services' =>
                $this->providerService(
                    $user,
                    $id
                ),

            'payouts' =>
                $this->providerPayout(
                    $user,
                    $id
                ),

            'wallet' =>
                $this->providerWalletEntry(
                    $user,
                    $id
                ),

            default =>
                abort(
                    Response::HTTP_NOT_FOUND
                ),
        };
    }

    private function residentInvoice(
        array $unitIds,
        int $id
    ): array {
        $invoice =
            UnitInvoice::query()
                ->with([
                    'unit:id,unit_number,title',
                    'building:id,title,currency',
                    'invoiceItems',
                    'paymentAllocations.payment:id,payment_number,amount,status,paid_at',
                ])
                ->whereIn(
                    'unit_id',
                    $unitIds
                )
                ->findOrFail(
                    $id
                );

        return [
            'title' =>
                'صورتحساب '
                . $invoice
                    ->invoice_number,

            'subtitle' =>
                $this->presenter
                    ->unit(
                        $invoice->unit
                    ),

            'status' =>
                $this->presenter
                    ->statusLabel(
                        $invoice->status
                    ),

            'status_tone' =>
                $this->presenter
                    ->statusTone(
                        $invoice->status
                    ),

            'facts' => [
                'شماره صورتحساب' =>
                    $invoice
                        ->invoice_number,

                'ساختمان' =>
                    $invoice
                        ->building
                        ?->title
                    ?: '—',

                'واحد' =>
                    $this->presenter
                        ->unit(
                            $invoice->unit
                        ),

                'تاریخ صدور' =>
                    $this->presenter
                        ->date(
                            $invoice
                                ->issue_date
                        ),

                'سررسید' =>
                    $this->presenter
                        ->date(
                            $invoice
                                ->due_date
                        ),

                'مبلغ کل' =>
                    $this->presenter
                        ->money(
                            $invoice
                                ->total_amount
                        )
                    . ' '
                    . (
                        $invoice
                            ->building
                            ?->currency
                        ?: 'IRR'
                    ),

                'پرداخت‌شده' =>
                    $this->presenter
                        ->money(
                            $invoice
                                ->paid_amount
                        ),

                'مانده' =>
                    $this->presenter
                        ->money(
                            $invoice
                                ->outstanding_amount
                        ),
            ],

            'sections' => [
                [
                    'title' =>
                        'اقلام صورتحساب',

                    'type' =>
                        'table',

                    'columns' => [
                        'عنوان',
                        'تعداد',
                        'مبلغ واحد',
                        'مبلغ کل',
                    ],

                    'rows' =>
                        $invoice
                            ->invoiceItems
                            ->map(
                                fn ($item): array => [
                                    $item->title,
                                    (string) $item
                                        ->quantity,
                                    $this->presenter
                                        ->money(
                                            $item
                                                ->unit_amount
                                        ),
                                    $this->presenter
                                        ->money(
                                            $item
                                                ->total_amount
                                        ),
                                ]
                            )
                            ->all(),
                ],
                [
                    'title' =>
                        'تخصیص پرداخت‌ها',

                    'type' =>
                        'timeline',

                    'rows' =>
                        $invoice
                            ->paymentAllocations
                            ->map(
                                fn ($allocation): array => [
                                    'title' =>
                                        $allocation
                                            ->payment
                                            ?->payment_number
                                        ?: 'پرداخت',

                                    'meta' =>
                                        $this->presenter
                                            ->money(
                                                $allocation
                                                    ->amount
                                            )
                                        . ' • '
                                        . $this->presenter
                                            ->statusLabel(
                                                $allocation
                                                    ->payment
                                                    ?->status
                                            ),

                                    'time' =>
                                        $this->presenter
                                            ->dateTime(
                                                $allocation
                                                    ->payment
                                                    ?->paid_at
                                                ?? $allocation
                                                    ->created_at
                                            ),
                                ]
                            )
                            ->all(),
                ],
            ],
        ];
    }

    private function residentReservation(
        User $user,
        array $unitIds,
        int $id
    ): array {
        $reservation =
            FacilityReservation::query()
                ->with([
                    'buildingFacility:id,building_id,title,default_price,requires_payment,requires_approval',
                    'unit:id,unit_number,title',
                    'reservationCancellations.cancelledBy:id,first_name,last_name',
                ])
                ->where(
                    'user_id',
                    $user->getKey()
                )
                ->whereIn(
                    'unit_id',
                    $unitIds
                )
                ->findOrFail(
                    $id
                );

        return [
            'title' =>
                $reservation
                    ->buildingFacility
                    ?->title
                ?: 'رزرو امکانات',

            'subtitle' =>
                $this->presenter
                    ->unit(
                        $reservation->unit
                    ),

            'status' =>
                $this->presenter
                    ->statusLabel(
                        $reservation->status
                    ),

            'status_tone' =>
                $this->presenter
                    ->statusTone(
                        $reservation->status
                    ),

            'facts' => [
                'تاریخ رزرو' =>
                    $this->presenter
                        ->date(
                            $reservation
                                ->reservation_date
                        ),

                'بازه زمانی' =>
                    substr(
                        (string) $reservation
                            ->start_time,
                        0,
                        5
                    )
                    . ' تا '
                    . substr(
                        (string) $reservation
                            ->end_time,
                        0,
                        5
                    ),

                'مبلغ نهایی' =>
                    $this->presenter
                        ->money(
                            $reservation
                                ->final_amount
                        )
                    . ' IRR',

                'نوع تأیید' =>
                    $this->presenter
                        ->enumValue(
                            $reservation
                                ->approval_type
                        ),

                'توضیحات' =>
                    $reservation
                        ->description
                    ?: '—',
            ],

            'sections' => [
                [
                    'title' =>
                        'تاریخچه لغو و بازپرداخت',

                    'type' =>
                        'timeline',

                    'rows' =>
                        $reservation
                            ->reservationCancellations
                            ->map(
                                fn ($cancellation): array => [
                                    'title' =>
                                        $cancellation
                                            ->reason
                                        ?: 'لغو رزرو',

                                    'meta' =>
                                        'جریمه: '
                                        . $this->presenter
                                            ->money(
                                                $cancellation
                                                    ->cancellation_fee
                                            )
                                        . ' • بازپرداخت: '
                                        . $this->presenter
                                            ->money(
                                                $cancellation
                                                    ->refund_amount
                                            )
                                        . ' • '
                                        . $this->presenter
                                            ->statusLabel(
                                                $cancellation
                                                    ->refund_status
                                            ),

                                    'time' =>
                                        $this->presenter
                                            ->dateTime(
                                                $cancellation
                                                    ->cancelled_at
                                            ),
                                ]
                            )
                            ->all(),
                ],
            ],
        ];
    }

    private function residentGuest(
        array $unitIds,
        int $id
    ): array {
        $visit =
            GuestVisit::query()
                ->with([
                    'guest:id,first_name,last_name,mobile,vehicle_plate',
                    'unit:id,unit_number,title',
                    'guestAccessLogs.verifiedBy:id,first_name,last_name',
                ])
                ->whereIn(
                    'unit_id',
                    $unitIds
                )
                ->findOrFail(
                    $id
                );

        $guestName =
            trim(
                (
                    $visit
                        ->guest
                        ?->first_name
                    ?? ''
                )
                . ' '
                . (
                    $visit
                        ->guest
                        ?->last_name
                    ?? ''
                )
            )
            ?: 'مهمان';

        return [
            'title' =>
                $guestName,

            'subtitle' =>
                $this->presenter
                    ->unit(
                        $visit->unit
                    ),

            'status' =>
                $this->presenter
                    ->statusLabel(
                        $visit->status
                    ),

            'status_tone' =>
                $this->presenter
                    ->statusTone(
                        $visit->status
                    ),

            'facts' => [
                'موبایل' =>
                    $visit
                        ->guest
                        ?->mobile
                    ?: '—',

                'پلاک خودرو' =>
                    $visit
                        ->guest
                        ?->vehicle_plate
                    ?: '—',

                'ورود مورد انتظار' =>
                    $this->presenter
                        ->dateTime(
                            $visit
                                ->expected_entry_at
                        ),

                'خروج مورد انتظار' =>
                    $this->presenter
                        ->dateTime(
                            $visit
                                ->expected_exit_at
                        ),

                'توضیحات' =>
                    $visit
                        ->description
                    ?: '—',
            ],

            'sections' => [
                [
                    'title' =>
                        'تاریخچه ورود و خروج',

                    'type' =>
                        'timeline',

                    'rows' =>
                        $visit
                            ->guestAccessLogs
                            ->sortBy(
                                'occurred_at'
                            )
                            ->map(
                                fn ($log): array => [
                                    'title' =>
                                        $this->presenter
                                            ->enumValue(
                                                $log
                                                    ->action
                                            ),

                                    'meta' =>
                                        trim(
                                            (
                                                $log->gate
                                                ?: ''
                                            )
                                            . ' '
                                            . (
                                                $log
                                                    ->vehicle_plate
                                                ?: ''
                                            )
                                        )
                                        ?: '—',

                                    'time' =>
                                        $this->presenter
                                            ->dateTime(
                                                $log
                                                    ->occurred_at
                                            ),
                                ]
                            )
                            ->all(),
                ],
            ],
        ];
    }

    private function residentService(
        User $user,
        array $unitIds,
        int $id
    ): array {
        $service =
            ServiceRequest::query()
                ->with([
                    'building:id,title',
                    'unit:id,unit_number,title',
                    'assignedTo:id,first_name,last_name,mobile',
                    'quotes.provider:id,first_name,last_name,mobile',
                    'walletPayment',
                ])
                ->where(
                    'requested_by',
                    $user->getKey()
                )
                ->whereIn(
                    'unit_id',
                    $unitIds
                )
                ->findOrFail(
                    $id
                );

        return $this->serviceDetail(
            $service,
            'resident'
        );
    }

    private function residentSupport(
        User $user,
        array $unitIds,
        int $id
    ): array {
        $ticket =
            SupportTicket::query()
                ->with([
                    'unit:id,unit_number,title',
                    'building:id,title',
                    'supportCategory:id,title',
                    'assignedTo:id,first_name,last_name',
                    'supportMessages' =>
                        fn ($query) =>
                            $query
                                ->where(
                                    'is_internal',
                                    false
                                )
                                ->with(
                                    'user:id,first_name,last_name'
                                )
                                ->oldest('id'),
                ])
                ->where(
                    'user_id',
                    $user->getKey()
                )
                ->whereIn(
                    'unit_id',
                    $unitIds
                )
                ->findOrFail(
                    $id
                );

        return [
            'title' =>
                $ticket->subject,

            'subtitle' =>
                $ticket
                    ->ticket_number,

            'status' =>
                $this->presenter
                    ->statusLabel(
                        $ticket->status
                    ),

            'status_tone' =>
                $this->presenter
                    ->statusTone(
                        $ticket->status
                    ),

            'facts' => [
                'ساختمان' =>
                    $ticket
                        ->building
                        ?->title
                    ?: '—',

                'واحد' =>
                    $this->presenter
                        ->unit(
                            $ticket->unit
                        ),

                'دسته‌بندی' =>
                    $ticket
                        ->supportCategory
                        ?->title
                    ?: 'عمومی',

                'اولویت' =>
                    $this->presenter
                        ->priorityLabel(
                            $ticket->priority
                        ),

                'کارشناس' =>
                    $this->presenter
                        ->person(
                            $ticket->assignedTo
                        ),

                'شرح اولیه' =>
                    $ticket
                        ->description
                    ?: '—',
            ],

            'sections' => [
                [
                    'title' =>
                        'گفتگو',

                    'type' =>
                        'timeline',

                    'rows' =>
                        $ticket
                            ->supportMessages
                            ->map(
                                fn ($message): array => [
                                    'title' =>
                                        $this->presenter
                                            ->person(
                                                $message->user
                                            ),

                                    'meta' =>
                                        $message
                                            ->message,

                                    'time' =>
                                        $this->presenter
                                            ->dateTime(
                                                $message
                                                    ->created_at
                                            ),
                                ]
                            )
                            ->all(),
                ],
            ],
        ];
    }

    private function residentWalletEntry(
        User $user,
        array $unitIds,
        int $id
    ): array {
        $walletIds =
            $this->residentWalletIds(
                $user,
                $unitIds
            );

        $entry =
            WalletEntry::query()
                ->with([
                    'wallet.owner',
                    'transfer',
                ])
                ->whereIn(
                    'wallet_id',
                    $walletIds
                )
                ->findOrFail(
                    $id
                );

        return $this->walletEntryDetail(
            $entry
        );
    }

    private function providerService(
        User $user,
        int $id
    ): array {
        $service =
            ServiceRequest::query()
                ->with([
                    'building:id,title',
                    'unit:id,unit_number,title',
                    'requestedBy:id,first_name,last_name,mobile',
                    'quotes' =>
                        fn ($query) =>
                            $query
                                ->where(
                                    'provider_user_id',
                                    $user->getKey()
                                )
                                ->with(
                                    'provider:id,first_name,last_name,mobile'
                                ),
                    'walletPayment',
                ])
                ->where(
                    'assigned_to',
                    $user->getKey()
                )
                ->findOrFail(
                    $id
                );

        return $this->serviceDetail(
            $service,
            'provider'
        );
    }

    private function providerPayout(
        User $user,
        int $id
    ): array {
        $payout =
            ProviderPayoutRequest::query()
                ->with([
                    'bankAccount',
                    'wallet',
                    'transfer',
                ])
                ->where(
                    'provider_user_id',
                    $user->getKey()
                )
                ->findOrFail(
                    $id
                );

        return [
            'title' =>
                'درخواست تسویه #'
                . $payout->getKey(),

            'subtitle' =>
                $this->presenter
                    ->money(
                        $payout->amount
                    )
                . ' IRR',

            'status' =>
                $this->presenter
                    ->statusLabel(
                        $payout->status
                    ),

            'status_tone' =>
                $this->presenter
                    ->statusTone(
                        $payout->status
                    ),

            'facts' => [
                'مبلغ درخواست' =>
                    $this->presenter
                        ->money(
                            $payout->amount
                        )
                    . ' IRR',

                'کارمزد' =>
                    $this->presenter
                        ->money(
                            $payout
                                ->fee_amount
                        ),

                'خالص پرداختی' =>
                    $this->presenter
                        ->money(
                            $payout
                                ->net_amount
                        ),

                'بانک' =>
                    $payout
                        ->bankAccount
                        ?->bank_name
                    ?: '—',

                'شبا' =>
                    $payout
                        ->bankAccount
                        ?->iban
                    ?: '—',

                'مرجع بانکی' =>
                    $payout
                        ->bank_reference
                    ?: '—',

                'تاریخ درخواست' =>
                    $this->presenter
                        ->dateTime(
                            $payout
                                ->created_at
                        ),

                'تاریخ پرداخت' =>
                    $this->presenter
                        ->dateTime(
                            $payout
                                ->paid_at
                        ),

                'علت رد' =>
                    $payout
                        ->rejection_reason
                    ?: '—',
            ],

            'sections' => [],
        ];
    }

    private function providerWalletEntry(
        User $user,
        int $id
    ): array {
        $walletIds =
            $user
                ->wallets()
                ->pluck('id')
                ->all();

        $entry =
            WalletEntry::query()
                ->with([
                    'wallet.owner',
                    'transfer',
                ])
                ->whereIn(
                    'wallet_id',
                    $walletIds
                )
                ->findOrFail(
                    $id
                );

        return $this->walletEntryDetail(
            $entry
        );
    }

    private function serviceDetail(
        ServiceRequest $service,
        string $perspective
    ): array {
        $payment =
            $service
                ->walletPayment;

        return [
            'title' =>
                $service->title,

            'subtitle' =>
                $service
                    ->request_number,

            'status' =>
                $this->presenter
                    ->statusLabel(
                        $service->status
                    ),

            'status_tone' =>
                $this->presenter
                    ->statusTone(
                        $service->status
                    ),

            'facts' => [
                'ساختمان' =>
                    $service
                        ->building
                        ?->title
                    ?: '—',

                'واحد' =>
                    $this->presenter
                        ->unit(
                            $service->unit
                        ),

                'اولویت' =>
                    $this->presenter
                        ->priorityLabel(
                            $service->priority
                        ),

                (
                    $perspective
                    === 'provider'
                        ? 'درخواست‌کننده'
                        : 'ارائه‌دهنده'
                ) =>
                    $this->presenter
                        ->person(
                            $perspective
                            === 'provider'
                                ? $service
                                    ->requestedBy
                                : $service
                                    ->assignedTo
                        ),

                'شرح درخواست' =>
                    $service
                        ->description
                    ?: '—',

                'وضعیت وجه' =>
                    $payment
                        ? $this->presenter
                            ->statusLabel(
                                $payment
                                    ->status
                            )
                        : 'بدون پرداخت',

                'مبلغ خدمت' =>
                    $payment
                        ? $this->presenter
                            ->money(
                                $payment
                                    ->amount
                            )
                            . ' IRR'
                        : '—',

                'سهم Provider' =>
                    $payment
                        ? $this->presenter
                            ->money(
                                $payment
                                    ->provider_amount
                            )
                        : '—',

                'کمیسیون پلتفرم' =>
                    $payment
                        ? $this->presenter
                            ->money(
                                $payment
                                    ->commission_amount
                            )
                        : '—',
            ],

            'sections' => [
                [
                    'title' =>
                        'پیشنهادهای قیمت',

                    'type' =>
                        'table',

                    'columns' => [
                        'Provider',
                        'مبلغ',
                        'کمیسیون',
                        'سهم Provider',
                        'وضعیت',
                    ],

                    'rows' =>
                        $service
                            ->quotes
                            ->map(
                                fn ($quote): array => [
                                    $this->presenter
                                        ->person(
                                            $quote
                                                ->provider
                                        ),

                                    $this->presenter
                                        ->money(
                                            $quote
                                                ->amount
                                        ),

                                    $this->presenter
                                        ->money(
                                            $quote
                                                ->commission_amount
                                        ),

                                    $this->presenter
                                        ->money(
                                            $quote
                                                ->provider_amount
                                        ),

                                    $this->presenter
                                        ->statusLabel(
                                            $quote
                                                ->status
                                        ),
                                ]
                            )
                            ->all(),
                ],
            ],
        ];
    }

    private function walletEntryDetail(
        WalletEntry $entry
    ): array {
        $transfer =
            $entry->transfer;

        return [
            'title' =>
                'تراکنش کیف پول #'
                . $entry->getKey(),

            'subtitle' =>
                $this->presenter
                    ->walletLabel(
                        $entry->wallet
                    ),

            'status' =>
                $this->presenter
                    ->enumValue(
                        $entry
                            ->entry_type
                    ) === 'credit'
                        ? 'واریز'
                        : 'برداشت',

            'status_tone' =>
                $this->presenter
                    ->enumValue(
                        $entry
                            ->entry_type
                    ) === 'credit'
                        ? 'success'
                        : 'danger',

            'facts' => [
                'مبلغ' =>
                    $this->presenter
                        ->money(
                            $entry->amount
                        ),

                'مانده بعد از تراکنش' =>
                    $this->presenter
                        ->money(
                            $entry
                                ->balance_after
                        ),

                'نوع انتقال' =>
                    $transfer
                        ? $this->presenter
                            ->enumValue(
                                $transfer
                                    ->type
                            )
                        : '—',

                'شرح' =>
                    $transfer
                        ?->description
                    ?: 'تراکنش کیف پول',

                'وضعیت انتقال' =>
                    $transfer
                        ? $this->presenter
                            ->statusLabel(
                                $transfer
                                    ->status
                            )
                        : '—',

                'زمان ثبت' =>
                    $this->presenter
                        ->dateTime(
                            $entry
                                ->created_at
                        ),

                'زمان تکمیل' =>
                    $this->presenter
                        ->dateTime(
                            $transfer
                                ?->completed_at
                        ),
            ],

            'sections' => [],
        ];
    }

    private function residentWalletIds(
        User $user,
        array $unitIds
    ): array {
        return $user
            ->wallets()
            ->pluck('id')
            ->merge(
                \App\Models\Wallet::query()
                    ->whereIn(
                        'owner_type',
                        [
                            (new \App\Models\Unit())
                                ->getMorphClass(),
                            \App\Models\Unit::class,
                        ]
                    )
                    ->whereIn(
                        'owner_id',
                        $unitIds
                    )
                    ->pluck('id')
            )
            ->unique()
            ->values()
            ->all();
    }
}
