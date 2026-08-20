@extends('portal.layouts.app')

@section(
    'title',
    ($operationConfig['title'] ?? 'عملیات')
    . ' | Buildino'
)

@section(
    'page-title',
    $operationConfig['title']
    ?? 'عملیات'
)

@section('sidebar-links')
    @php
        $resources =
            config(
                "portal_operations.{$area}",
                []
            );
    @endphp

    @foreach ($resources as $key => $item)
        <a
            href="{{
                route(
                    "portal.{$area}.operations.index",
                    [
                        'resource' => $key,
                    ]
                )
            }}"
            class="{{
                $resource === $key
                    ? 'is-active'
                    : ''
            }}"
        >
            @include(
                'management.partials.icon',
                [
                    'name' =>
                        $item['icon']
                        ?? 'grid',
                    'size' => 18,
                ]
            )

            <span>
                {{ $item['title'] }}
            </span>
        </a>
    @endforeach
@endsection

@section('content')
<section class="portal-operation-hero">
    <div>
        <span class="portal-eyebrow">
            SERVER-SIDE DATATABLE
        </span>

        <h2>
            {{
                $operationConfig['title']
                ?? 'عملیات'
            }}
        </h2>

        <p>
            {{
                $operationConfig['description']
                ?? ''
            }}
        </p>
    </div>

    <a
        href="{{
            route(
                "portal.{$area}.dashboard"
            )
        }}"
        class="portal-action-button"
    >
        بازگشت به داشبورد
    </a>
</section>

<section
    class="portal-section portal-datatable-section"
    data-dt-filter-scope
>
    <div class="portal-datatable-toolbar">
        @if (
            in_array(
                'status',
                $operationConfig['filters']
                ?? [],
                true
            )
        )
            <label>
                <span>وضعیت</span>

                <select
                    data-dt-filter="status"
                >
                    <option value="">
                        همه وضعیت‌ها
                    </option>
                    <option value="pending">در انتظار</option>
                    <option value="payment_pending">در انتظار پرداخت</option>
                    <option value="issued">صادر شده</option>
                    <option value="partial">پرداخت ناقص</option>
                    <option value="paid">پرداخت شده</option>
                    <option value="overdue">سررسید گذشته</option>
                    <option value="approved">تأیید شده</option>
                    <option value="confirmed">قطعی</option>
                    <option value="invited">دعوت شده</option>
                    <option value="entered">وارد شده</option>
                    <option value="exited">خارج شده</option>
                    <option value="open">باز</option>
                    <option value="assigned">تخصیص داده شده</option>
                    <option value="in_progress">در حال انجام</option>
                    <option value="awaiting_confirmation">در انتظار تأیید</option>
                    <option value="waiting_user">در انتظار کاربر</option>
                    <option value="resolved">حل شده</option>
                    <option value="closed">بسته شده</option>
                    <option value="cancelled">لغو شده</option>
                    <option value="rejected">رد شده</option>
                    <option value="settled">تسویه شده</option>
                    <option value="failed">ناموفق</option>
                </select>
            </label>
        @endif

        @if (
            in_array(
                'from',
                $operationConfig['filters']
                ?? [],
                true
            )
        )
            <label>
                <span>از تاریخ</span>
                <input
                    type="date"
                    data-dt-filter="from"
                >
            </label>
        @endif

        @if (
            in_array(
                'to',
                $operationConfig['filters']
                ?? [],
                true
            )
        )
            <label>
                <span>تا تاریخ</span>
                <input
                    type="date"
                    data-dt-filter="to"
                >
            </label>
        @endif

        <button
            type="button"
            class="portal-dt-reset"
            data-dt-reset
        >
            پاک‌کردن فیلتر
        </button>
    </div>

    <div class="portal-datatable-card">
        @include(
            'shared.server-datatable',
            [
                'id' =>
                    "{$area}-{$resource}-table",
                'url' =>
                    $dataUrl,
                'columns' =>
                    $operationConfig['columns']
                    ?? [],
                'pageLength' =>
                    15,
            ]
        )
    </div>
</section>
@endsection
