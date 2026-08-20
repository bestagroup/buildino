@extends('portal.layouts.app')

@section('title', 'پنل ارائه‌دهنده خدمات | Buildino')
@section('page-title', 'پنل ارائه‌دهنده خدمات')

@section('sidebar-links')
    <a href="#provider-wallet-history">
        @include(
            'management.partials.icon',
            [
                'name' => 'wallet',
                'size' => 18,
            ]
        )
        <span>گردش کیف پول</span>
    </a>

    <a href="#jobs">
        @include(
            'management.partials.icon',
            [
                'name' => 'tools',
                'size' => 18,
            ]
        )
        <span>کارهای من</span>
    </a>

    <a href="#settlement">
        @include(
            'management.partials.icon',
            [
                'name' => 'wallet',
                'size' => 18,
            ]
        )
        <span>تسویه و حساب بانکی</span>
    </a>
@endsection

@php
    $stats =
        $portalData['stats'];

    $money =
        static fn (
            int|float|string|null $value
        ): string =>
            number_format(
                (int) ($value ?? 0)
            );

    $statusLabel =
        static function (
            mixed $value
        ): string {
            $status =
                is_object($value)
                    ? (
                        $value->value
                        ?? (string) $value
                    )
                    : (string) $value;

            return match ($status) {
                'open' => 'باز',
                'assigned' => 'تخصیص داده شده',
                'in_progress' => 'در حال انجام',
                'awaiting_confirmation' => 'در انتظار تأیید مشتری',
                'completed' => 'تکمیل شده',
                'cancelled' => 'لغو شده',
                'pending' => 'در انتظار بررسی',
                'approved' => 'تأیید شده',
                'paid' => 'پرداخت شده',
                'rejected' => 'رد شده',
                default => $status ?: '—',
            };
        };

    $statusTone =
        static function (
            mixed $value
        ): string {
            $status =
                is_object($value)
                    ? (
                        $value->value
                        ?? (string) $value
                    )
                    : (string) $value;

            return match ($status) {
                'completed',
                'paid',
                'approved' => 'success',

                'assigned',
                'in_progress',
                'awaiting_confirmation',
                'pending' => 'warning',

                'rejected' => 'danger',

                'cancelled' => 'muted',

                default => 'info',
            };
        };
@endphp

<section class="portal-hero portal-hero--provider">
    <div class="portal-hero__copy">
        <span class="portal-eyebrow">
            SERVICE PROVIDER
        </span>

        <h2>
            مرکز کار ارائه‌دهنده
        </h2>

        <p>
            درخواست‌های تخصیص‌یافته، پیشنهاد قیمت، وضعیت انجام کار،
            درآمد کیف پول و درخواست‌های تسویه خود را مدیریت کنید.
        </p>

        <div class="portal-hero__meta">
            <span>
                {{
                    number_format(
                        $stats[
                            'active_jobs'
                        ]
                    )
                }}
                کار فعال
            </span>

            <span>
                بروزرسانی:
                {{
                    $portalData[
                        'generated_at_jalali'
                    ]
                }}
            </span>
        </div>
    </div>

    <div class="portal-hero__actions">
        <button
            type="button"
            class="portal-action-button"
            data-bs-toggle="modal"
            data-bs-target="#bankAccountModal"
        >
            @include(
                'management.partials.icon',
                [
                    'name' => 'money',
                    'size' => 18,
                ]
            )
            حساب بانکی جدید
        </button>

        <button
            type="button"
            class="portal-action-button portal-action-button--primary"
            data-bs-toggle="modal"
            data-bs-target="#payoutModal"
            @disabled(
                $portalData[
                    'bank_accounts'
                ]
                    ->where(
                        'is_verified',
                        true
                    )
                    ->isEmpty()
            )
        >
            @include(
                'management.partials.icon',
                [
                    'name' => 'wallet',
                    'size' => 18,
                ]
            )
            درخواست تسویه
        </button>
    </div>
</section>

<section class="portal-stat-grid">
    <article class="portal-stat-card">
        <span class="portal-stat-card__icon">
            @include(
                'management.partials.icon',
                [
                    'name' => 'tools',
                    'size' => 21,
                ]
            )
        </span>

        <div>
            <span>کارهای فعال</span>
            <strong>
                {{
                    number_format(
                        $stats[
                            'active_jobs'
                        ]
                    )
                }}
            </strong>
            <small>درخواست</small>
        </div>
    </article>

    <article class="portal-stat-card portal-stat-card--success">
        <span class="portal-stat-card__icon">
            @include(
                'management.partials.icon',
                [
                    'name' => 'chart',
                    'size' => 21,
                ]
            )
        </span>

        <div>
            <span>کارهای تکمیل‌شده</span>
            <strong>
                {{
                    number_format(
                        $stats[
                            'completed_jobs'
                        ]
                    )
                }}
            </strong>
            <small>خدمت</small>
        </div>
    </article>

    <article class="portal-stat-card portal-stat-card--wallet">
        <span class="portal-stat-card__icon">
            @include(
                'management.partials.icon',
                [
                    'name' => 'wallet',
                    'size' => 21,
                ]
            )
        </span>

        <div>
            <span>درآمد تسویه‌شده</span>
            <strong>
                {{
                    $money(
                        $stats[
                            'settled_earnings'
                        ]
                    )
                }}
            </strong>
            <small>IRR</small>
        </div>
    </article>

    <article class="portal-stat-card portal-stat-card--warning">
        <span class="portal-stat-card__icon">
            @include(
                'management.partials.icon',
                [
                    'name' => 'money',
                    'size' => 21,
                ]
            )
        </span>

        <div>
            <span>مبلغ در انتظار تسویه</span>
            <strong>
                {{
                    $money(
                        $stats[
                            'pending_payout'
                        ]
                    )
                }}
            </strong>
            <small>IRR</small>
        </div>
    </article>
</section>

<section
    class="portal-section"
    id="provider-wallet-history"
>
    <div class="portal-section__heading">
        <div>
            <span class="portal-eyebrow">
                WALLET LEDGER
            </span>
            <h3>
                گردش کیف پول ارائه‌دهنده
            </h3>
        </div>

        <div class="portal-section__actions">
            <span class="portal-section__note">
                آخرین ورودی و خروجی‌های کیف پول شخصی شما
            </span>

            <a
                href="{{
                    route(
                        'portal.provider.operations.index',
                        [
                            'resource' =>
                                'wallet',
                        ]
                    )
                }}"
                class="portal-section-link"
            >
                مشاهده همه تراکنش‌ها
            </a>
        </div>
    </div>

    <article class="portal-wallet-history-card portal-wallet-history-card--wide">
        <header>
            <div>
                @include(
                    'management.partials.icon',
                    [
                        'name' => 'wallet',
                        'size' => 19,
                    ]
                )

                <div>
                    <strong>
                        کیف پول Provider
                    </strong>

                    <span>
                        قابل استفاده:
                        {{
                            $money(
                                data_get(
                                    $portalData,
                                    'personal_wallet.available_balance',
                                    0
                                )
                            )
                        }}
                        {{
                            data_get(
                                $portalData,
                                'personal_wallet.currency',
                                'IRR'
                            )
                        }}
                        •
                        قفل‌شده:
                        {{
                            $money(
                                data_get(
                                    $portalData,
                                    'personal_wallet.locked_balance',
                                    0
                                )
                            )
                        }}
                    </span>
                </div>
            </div>
        </header>

        <div class="portal-wallet-entry-list">
            @forelse (
                data_get(
                    $portalData,
                    'personal_wallet.entries',
                    []
                )
                as $entry
            )
                <div class="portal-wallet-entry">
                    <span class="portal-wallet-entry__type portal-wallet-entry__type--{{
                        $entry['entry_type']
                    }}">
                        {{
                            $entry['entry_type']
                                === 'credit'
                                    ? '+'
                                    : '-'
                        }}
                    </span>

                    <div>
                        <strong>
                            {{
                                $money(
                                    $entry['amount']
                                )
                            }}
                        </strong>

                        <span>
                            {{
                                $entry['description']
                                ?: 'تراکنش کیف پول'
                            }}
                        </span>
                    </div>

                    <time>
                        {{
                            $entry[
                                'created_at_jalali'
                            ]
                            ?: '—'
                        }}
                    </time>
                </div>
            @empty
                <div class="portal-empty-mini">
                    هنوز تراکنشی در کیف پول Provider ثبت نشده است.
                </div>
            @endforelse
        </div>
    </article>
</section>

<section
    class="portal-section"
    id="jobs"
>
    <div class="portal-section__heading">
        <div>
            <span class="portal-eyebrow">
                ASSIGNED JOBS
            </span>
            <h3>
                درخواست‌های تخصیص‌یافته
            </h3>
        </div>

        <div class="portal-section__actions">
            <span class="portal-section__note">
                Workflow هر کار از API اصلی Service Marketplace اجرا می‌شود.
            </span>

            <a
                href="{{
                    route(
                        'portal.provider.operations.index',
                        [
                            'resource' =>
                                'services',
                        ]
                    )
                }}"
                class="portal-section-link"
            >
                مشاهده همه کارها
            </a>
        </div>
    </div>

    <div class="portal-job-grid">
        @forelse (
            $portalData[
                'service_requests'
            ]
            as $service
        )
            @php
                $serviceStatus =
                    is_object(
                        $service->status
                    )
                        ? $service
                            ->status
                            ->value
                        : $service
                            ->status;

                $myQuote =
                    $service
                        ->quotes
                        ->first();

                $providerPaymentStatus =
                    $service
                        ->walletPayment
                        ? (
                            is_object(
                                $service
                                    ->walletPayment
                                    ->status
                            )
                                ? $service
                                    ->walletPayment
                                    ->status
                                    ->value
                                : $service
                                    ->walletPayment
                                    ->status
                        )
                        : null;
            @endphp

            <article class="portal-job-card">
                <div class="portal-job-card__header">
                    <div>
                        <span>
                            {{
                                $service
                                    ->request_number
                            }}
                        </span>

                        <h4>
                            {{ $service->title }}
                        </h4>
                    </div>

                    <span class="portal-status portal-status--{{
                        $statusTone(
                            $service->status
                        )
                    }}">
                        {{
                            $statusLabel(
                                $service->status
                            )
                        }}
                    </span>
                </div>

                <p>
                    {{
                        \Illuminate\Support\Str::limit(
                            $service
                                ->description
                                ?: 'بدون توضیحات',
                            180
                        )
                    }}
                </p>

                <div class="portal-job-card__meta">
                    <div>
                        <span>ساختمان</span>
                        <strong>
                            {{
                                $service
                                    ->building
                                    ?->title
                                ?: '—'
                            }}
                        </strong>
                    </div>

                    <div>
                        <span>واحد</span>
                        <strong>
                            {{
                                $service
                                    ->unit
                                    ?->title
                                ?: $service
                                    ->unit
                                    ?->unit_number
                                ?: '—'
                            }}
                        </strong>
                    </div>

                    <div>
                        <span>درخواست‌کننده</span>
                        <strong>
                            {{
                                trim(
                                    (
                                        $service
                                            ->requestedBy
                                            ?->first_name
                                        ?? ''
                                    )
                                    . ' '
                                    . (
                                        $service
                                            ->requestedBy
                                            ?->last_name
                                        ?? ''
                                    )
                                )
                                ?: '—'
                            }}
                        </strong>
                    </div>
                </div>

                @if ($myQuote)
                    <div class="portal-quote-summary">
                        <span>
                            پیشنهاد فعلی شما
                        </span>

                        <strong>
                            {{
                                $money(
                                    $myQuote
                                        ->amount
                                )
                            }}
                            IRR
                        </strong>

                        <em>
                            {{
                                is_object(
                                    $myQuote
                                        ->status
                                )
                                    ? $myQuote
                                        ->status
                                        ->value
                                    : $myQuote
                                        ->status
                            }}
                        </em>
                    </div>
                @endif

                <div class="portal-job-card__actions">
                    @if (
                        in_array(
                            $serviceStatus,
                            [
                                'assigned',
                                'open',
                            ],
                            true
                        )
                    )
                        <button
                            type="button"
                            data-provider-quote
                            data-service-id="{{
                                $service->id
                            }}"
                            data-service-title="{{
                                $service->title
                            }}"
                        >
                            پیشنهاد قیمت
                        </button>
                    @endif

                    @if (
                        $serviceStatus
                        === 'assigned'
                        && $providerPaymentStatus
                        === 'locked'
                    )
                        <button
                            type="button"
                            data-provider-action="start"
                            data-service-id="{{
                                $service->id
                            }}"
                            class="is-primary"
                        >
                            شروع کار
                        </button>
                    @elseif (
                        $serviceStatus
                        === 'assigned'
                        && $providerPaymentStatus
                        !== 'locked'
                    )
                        <span class="portal-job-awaiting">
                            در انتظار پذیرش پیشنهاد و قفل مبلغ
                        </span>
                    @endif

                    @if (
                        $serviceStatus
                        === 'in_progress'
                    )
                        <button
                            type="button"
                            data-provider-action="finish"
                            data-service-id="{{
                                $service->id
                            }}"
                            class="is-success"
                        >
                            اعلام پایان کار
                        </button>
                    @endif

                    @if (
                        $serviceStatus
                        === 'awaiting_confirmation'
                    )
                        <span class="portal-job-awaiting">
                            در انتظار تأیید درخواست‌کننده
                        </span>
                    @endif
                </div>
            </article>
        @empty
            <div class="portal-empty-state">
                هنوز کاری به شما تخصیص داده نشده است.
            </div>
        @endforelse
    </div>
</section>

<section
    class="portal-section"
    id="settlement"
>
    <div class="portal-section__heading">
        <div>
            <span class="portal-eyebrow">
                SETTLEMENT
            </span>
            <h3>
                حساب بانکی و تسویه
            </h3>
        </div>

        <a
            href="{{
                route(
                    'portal.provider.operations.index',
                    [
                        'resource' =>
                            'payouts',
                    ]
                )
            }}"
            class="portal-section-link"
        >
            همه درخواست‌های تسویه
        </a>
    </div>

    <div class="portal-settlement-grid">
        <article class="portal-list-card">
            <header>
                <div>
                    @include(
                        'management.partials.icon',
                        [
                            'name' => 'money',
                            'size' => 19,
                        ]
                    )
                    <strong>
                        حساب‌های بانکی
                    </strong>
                </div>

                <span>
                    {{
                        $stats[
                            'verified_bank_accounts'
                        ]
                    }}
                    تأییدشده
                </span>
            </header>

            <div class="portal-list">
                @forelse (
                    $portalData[
                        'bank_accounts'
                    ]
                    as $account
                )
                    <div class="portal-bank-account">
                        <div>
                            <strong>
                                {{
                                    $account
                                        ->bank_name
                                    ?: 'حساب بانکی'
                                }}
                            </strong>

                            <span dir="ltr">
                                {{
                                    $account
                                        ->iban
                                }}
                            </span>
                        </div>

                        <div>
                            @if ($account->is_default)
                                <span class="portal-status portal-status--info">
                                    پیش‌فرض
                                </span>
                            @endif

                            <span class="portal-status portal-status--{{
                                $account->is_verified
                                    ? 'success'
                                    : 'warning'
                            }}">
                                {{
                                    $account->is_verified
                                        ? 'تأیید شده'
                                        : 'در انتظار تأیید'
                                }}
                            </span>
                        </div>
                    </div>
                @empty
                    <div class="portal-empty-mini">
                        حساب بانکی ثبت نشده است.
                    </div>
                @endforelse
            </div>
        </article>

        <article class="portal-list-card">
            <header>
                <div>
                    @include(
                        'management.partials.icon',
                        [
                            'name' => 'wallet',
                            'size' => 19,
                        ]
                    )
                    <strong>
                        درخواست‌های تسویه
                    </strong>
                </div>
            </header>

            <div class="portal-list">
                @forelse (
                    $portalData[
                        'payouts'
                    ]
                    as $payout
                )
                    <div class="portal-list-item">
                        <div>
                            <strong>
                                {{
                                    $money(
                                        $payout
                                            ->amount
                                    )
                                }}
                                IRR
                            </strong>

                            <span>
                                {{
                                    $payout
                                        ->created_at
                                        ?->format(
                                            'Y-m-d H:i'
                                        )
                                }}
                            </span>
                        </div>

                        <span class="portal-status portal-status--{{
                            $statusTone(
                                $payout->status
                            )
                        }}">
                            {{
                                $statusLabel(
                                    $payout->status
                                )
                            }}
                        </span>
                    </div>
                @empty
                    <div class="portal-empty-mini">
                        درخواست تسویه‌ای ندارید.
                    </div>
                @endforelse
            </div>
        </article>
    </div>
</section>

{{-- Quote Modal --}}
<div
    class="modal fade"
    id="providerQuoteModal"
    tabindex="-1"
>
    <div class="modal-dialog modal-dialog-centered">
        <form
            class="modal-content portal-modal"
            data-portal-form="provider-quote"
        >
            <div class="modal-header">
                <div>
                    <span class="portal-eyebrow">
                        QUOTE
                    </span>

                    <h5 class="modal-title">
                        پیشنهاد قیمت
                    </h5>
                </div>

                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="modal"
                ></button>
            </div>

            <div class="modal-body portal-form-grid">
                <input
                    type="hidden"
                    name="service_request_id"
                >

                <div class="portal-topup-target portal-field--wide">
                    <span>
                        درخواست:
                    </span>
                    <strong data-provider-quote-title>
                        —
                    </strong>
                </div>

                <label class="portal-field portal-field--wide">
                    <span>مبلغ پیشنهادی</span>
                    <input
                        type="number"
                        name="amount"
                        min="1"
                        dir="ltr"
                        required
                    >
                </label>

                <label class="portal-field portal-field--wide">
                    <span>اعتبار پیشنهاد تا</span>
                    <input
                        type="datetime-local"
                        name="valid_until"
                    >
                </label>

                <label class="portal-field portal-field--wide">
                    <span>توضیحات</span>
                    <textarea
                        name="notes"
                        rows="4"
                    ></textarea>
                </label>
            </div>

            <div class="modal-footer">
                <button
                    type="button"
                    class="btn btn-light"
                    data-bs-dismiss="modal"
                >
                    انصراف
                </button>

                <button
                    type="submit"
                    class="portal-primary-button"
                >
                    ثبت پیشنهاد
                </button>
            </div>
        </form>
    </div>
</div>

{{-- Bank Account Modal --}}
<div
    class="modal fade"
    id="bankAccountModal"
    tabindex="-1"
>
    <div class="modal-dialog modal-dialog-centered">
        <form
            class="modal-content portal-modal"
            data-portal-form="provider-bank"
        >
            <div class="modal-header">
                <h5 class="modal-title">
                    ثبت حساب بانکی
                </h5>
                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="modal"
                ></button>
            </div>

            <div class="modal-body portal-form-grid">
                <label class="portal-field">
                    <span>نام بانک</span>
                    <input name="bank_name">
                </label>

                <label class="portal-field">
                    <span>نام صاحب حساب</span>
                    <input
                        name="account_holder_name"
                        required
                    >
                </label>

                <label class="portal-field portal-field--wide">
                    <span>شماره شبا</span>
                    <input
                        name="iban"
                        dir="ltr"
                        placeholder="IR..."
                        required
                    >
                </label>

                <label class="portal-field">
                    <span>شماره حساب</span>
                    <input
                        name="account_number"
                        dir="ltr"
                    >
                </label>

                <label class="portal-field">
                    <span>شماره کارت</span>
                    <input
                        name="card_number"
                        dir="ltr"
                    >
                </label>

                <label class="portal-check portal-field--wide">
                    <input
                        type="checkbox"
                        name="is_default"
                        value="1"
                    >
                    <span>
                        حساب پیش‌فرض من باشد
                    </span>
                </label>
            </div>

            <div class="modal-footer">
                <button
                    type="button"
                    class="btn btn-light"
                    data-bs-dismiss="modal"
                >
                    انصراف
                </button>

                <button
                    type="submit"
                    class="portal-primary-button"
                >
                    ثبت حساب
                </button>
            </div>
        </form>
    </div>
</div>

{{-- Payout Modal --}}
<div
    class="modal fade"
    id="payoutModal"
    tabindex="-1"
>
    <div class="modal-dialog modal-dialog-centered">
        <form
            class="modal-content portal-modal"
            data-portal-form="provider-payout"
        >
            <div class="modal-header">
                <h5 class="modal-title">
                    درخواست تسویه
                </h5>
                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="modal"
                ></button>
            </div>

            <div class="modal-body portal-form-grid">
                <label class="portal-field portal-field--wide">
                    <span>حساب بانکی تأییدشده</span>

                    <select
                        name="provider_bank_account_id"
                        required
                    >
                        @foreach (
                            $portalData[
                                'bank_accounts'
                            ]
                                ->where(
                                    'is_verified',
                                    true
                                )
                            as $account
                        )
                            <option
                                value="{{
                                    $account->id
                                }}"
                            >
                                {{
                                    $account
                                        ->bank_name
                                    ?: 'حساب'
                                }}
                                -
                                {{
                                    $account
                                        ->iban
                                }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <label class="portal-field portal-field--wide">
                    <span>مبلغ تسویه</span>
                    <input
                        type="number"
                        name="amount"
                        min="1"
                        dir="ltr"
                        required
                    >
                </label>
            </div>

            <div class="modal-footer">
                <button
                    type="button"
                    class="btn btn-light"
                    data-bs-dismiss="modal"
                >
                    انصراف
                </button>

                <button
                    type="submit"
                    class="portal-primary-button"
                >
                    ثبت درخواست تسویه
                </button>
            </div>
        </form>
    </div>
</div>
