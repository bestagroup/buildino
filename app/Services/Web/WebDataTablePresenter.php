<?php

namespace App\Services\Web;

use App\Models\Unit;
use App\Models\User;
use App\Models\Wallet;
use App\Support\Jalali\JalaliDateFormatter;
use BackedEnum;
use DateTimeInterface;

final class WebDataTablePresenter
{
    public function __construct(
        private readonly JalaliDateFormatter $jalali
    ) {
    }

    public function money(
        int|float|string|null $value
    ): string {
        return number_format(
            (int) ($value ?? 0)
        );
    }

    public function person(
        ?User $user
    ): string {
        if (! $user) {
            return '—';
        }

        return trim(
            ($user->first_name ?? '')
            . ' '
            . ($user->last_name ?? '')
        )
            ?: ($user->mobile ?? '—');
    }

    public function unit(
        ?Unit $unit
    ): string {
        if (! $unit) {
            return '—';
        }

        return $unit->title
            ?: (
                $unit->unit_number
                    ? 'واحد ' . $unit->unit_number
                    : 'واحد #' . $unit->getKey()
            );
    }

    public function date(
        ?DateTimeInterface $date
    ): string {
        return $this->jalali
            ->date(
                $date
            )
            ?: '—';
    }

    public function dateTime(
        ?DateTimeInterface $date
    ): string {
        return $this->jalali
            ->dateTime(
                $date
            )
            ?: '—';
    }

    public function enumValue(
        mixed $value
    ): string {
        if ($value instanceof BackedEnum) {
            return (string) $value->value;
        }

        return (string) (
            $value
            ?? ''
        );
    }

    public function statusLabel(
        mixed $value
    ): string {
        return match (
            $this->enumValue(
                $value
            )
        ) {
            'draft' => 'پیش‌نویس',
            'pending' => 'در انتظار',
            'payment_pending' => 'در انتظار پرداخت',
            'issued' => 'صادر شده',
            'partial' => 'پرداخت ناقص',
            'paid' => 'پرداخت شده',
            'overdue' => 'سررسید گذشته',
            'approved' => 'تأیید شده',
            'confirmed' => 'قطعی',
            'rejected' => 'رد شده',
            'cancelled' => 'لغو شده',
            'expired' => 'منقضی',
            'invited' => 'دعوت شده',
            'entered' => 'وارد شده',
            'exited' => 'خارج شده',
            'open' => 'باز',
            'assigned' => 'تخصیص داده شده',
            'in_progress' => 'در حال انجام',
            'awaiting_confirmation' => 'در انتظار تأیید',
            'waiting_user' => 'در انتظار کاربر',
            'resolved' => 'حل شده',
            'closed' => 'بسته شده',
            'locked' => 'وجه قفل شده',
            'settled' => 'تسویه شده',
            'failed' => 'ناموفق',
            'refunded' => 'بازپرداخت شده',
            'processing' => 'در حال پردازش',
            default =>
                $this->enumValue(
                    $value
                )
                ?: '—',
        };
    }

    public function statusTone(
        mixed $value
    ): string {
        return match (
            $this->enumValue(
                $value
            )
        ) {
            'paid',
            'completed',
            'confirmed',
            'approved',
            'settled',
            'resolved',
            'closed',
            'exited' => 'success',

            'pending',
            'payment_pending',
            'partial',
            'open',
            'assigned',
            'in_progress',
            'awaiting_confirmation',
            'waiting_user',
            'invited',
            'entered',
            'locked',
            'processing' => 'warning',

            'overdue',
            'rejected',
            'failed' => 'danger',

            'cancelled',
            'expired',
            'refunded' => 'muted',

            default => 'info',
        };
    }

    public function priorityLabel(
        mixed $value
    ): string {
        return match (
            $this->enumValue(
                $value
            )
        ) {
            'low' => 'کم',
            'normal',
            'medium' => 'عادی',
            'high' => 'زیاد',
            'urgent' => 'فوری',
            default =>
                $this->enumValue(
                    $value
                )
                ?: '—',
        };
    }

    public function walletLabel(
        ?Wallet $wallet
    ): string {
        if (! $wallet) {
            return 'کیف پول';
        }

        $owner =
            $wallet->owner;

        return match (
            $wallet->ownerKind()
        ) {
            'user' =>
                'کیف پول شخصی',

            'unit' =>
                'کیف پول '
                . (
                    $owner instanceof Unit
                        ? $this->unit(
                            $owner
                        )
                        : 'واحد'
                ),

            'building' =>
                'کیف پول ساختمان',

            'platform' =>
                'کیف پول پلتفرم',

            default =>
                'کیف پول #' . $wallet->getKey(),
        };
    }
}
