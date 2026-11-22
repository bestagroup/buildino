@extends('portal.layouts.app')
@section('title', 'واحدهای من | Buildino')
@section('page-title', 'واحدهای من')
@section('content')
@php $money = static fn ($value) => strtr(number_format((int) ($value ?? 0)), array_combine(range(0,9), ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'])); @endphp
<div data-resident-workspace data-resident-resource="units">
<nav class="portal-breadcrumb" aria-label="مسیر صفحه"><a href="{{ route('portal.resident.dashboard') }}">خانه من</a><span>/</span><strong>واحدهای من</strong></nav>
@if(empty($portalData['units']))
<section class="resident-panel resident-empty"><h3>هنوز به واحدی متصل نشده‌اید</h3><button type="button" class="portal-primary-button" data-invitation-open>واردکردن کد دعوت</button></section>
@endif
<section
    class="portal-section"
    id="units"
>
    <div class="portal-section__heading">
        <div>
            <span class="portal-eyebrow">
                واحدهای من
            </span>
            <h3>
                واحدهای من
            </h3>
        </div>
    </div>

    <div class="portal-unit-grid">
        @foreach ($portalData['units'] as $unit)
            <article
                class="portal-unit-card"
                data-unit-id="{{ $unit['id'] }}"
                data-building-id="{{ $unit['building_id'] }}"
            >
                <div class="portal-unit-card__top">
                    <span class="portal-unit-card__role">
                        {{
                            $unit[
                                'relationship'
                            ]['label']
                        }}
                    </span>

                    <span>
                        {{
                            $unit['building_title']
                        }}
                    </span>
                </div>

                <h4>
                    {{ $unit['title'] }}
                </h4>

                <p>
                    {{
                        $unit['complex_title']
                        ?: '—'
                    }}
                    •
                    {{
                        $unit['block_title']
                        ?: '—'
                    }}
                    •
                    {{
                        $unit['floor_title']
                        ?: '—'
                    }}
                </p>

                <div class="portal-unit-card__facts">
                    <div>
                        <span>متراژ</span>
                        <strong>
                            {{
                                number_format(
                                    $unit['area'],
                                    0
                                )
                            }}
                            متر
                        </strong>
                    </div>

                    <div>
                        <span>کیف پول واحد</span>
                        <strong>
                            {{
                                $money(
                                    data_get(
                                        $unit,
                                        'wallet.available_balance'
                                    )
                                )
                            }}
                            {{ data_get($unit, 'wallet.currency') === 'IRT' ? 'تومان' : 'ریال' }}
                        </strong>
                    </div>
                </div>

                @if (
                    $portalData[
                        'payment_gateway'
                    ]['enabled']
                )
                    <button
                        type="button"
                        class="portal-inline-action"
                        data-open-topup
                        data-unit-id="{{ $unit['id'] }}"
                        data-building-id="{{ $unit['building_id'] }}"
                        data-unit-title="{{ $unit['title'] }}"
                    >
                        افزایش موجودی
                    </button>
                @endif
                <a class="portal-action-button" href="{{ route('portal.resident.operations.index', ['resource'=>'invoices', 'unit_id'=>$unit['id']]) }}">صورتحساب‌های این واحد</a>
            </article>
        @endforeach
    </div>
</section>


</div>
@include('portal.resident.forms', ['resource'=>'units'])
@endsection
