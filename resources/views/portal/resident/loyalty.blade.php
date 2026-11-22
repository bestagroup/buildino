@extends('portal.layouts.app')
@section('title', 'باشگاه وفاداری | Buildino')
@section('page-title', 'باشگاه وفاداری')
@section('content')
@php $statusLabel = app(\App\Services\Web\WebDataTablePresenter::class)->statusLabel(...); @endphp
<div data-resident-workspace data-resident-resource="loyalty">
<nav class="portal-breadcrumb" aria-label="مسیر صفحه"><a href="{{ route('portal.resident.dashboard') }}">خانه من</a><span>/</span><strong>باشگاه وفاداری</strong></nav>
<section class="portal-section" id="loyalty">
    <div class="portal-section__heading">
        <div>
            <span class="portal-eyebrow">باشگاه وفاداری</span>
            <h3>باشگاه وفاداری</h3>
        </div>

        <div class="portal-section__actions">
            <span class="portal-section__note">
                امتیاز قابل استفاده:
                <strong data-loyalty-balance>
                    {{ number_format(data_get($portalData, 'loyalty.balance', 0)) }}
                </strong>
            </span>
        </div>
    </div>

    <div class="portal-unit-grid">
        @forelse (data_get($portalData, 'loyalty.rewards', []) as $reward)
            <article class="portal-unit-card">
                <div class="portal-unit-card__top">
                    <span class="portal-unit-card__role">
                        {{ number_format($reward['required_points']) }} امتیاز
                    </span>
                    <span>جایزه</span>
                </div>

                <h4>{{ $reward['title'] }}</h4>
                <p>{{ $reward['description'] ?: 'بدون توضیح تکمیلی' }}</p>

                <button
                    type="button"
                    class="portal-inline-action"
                    data-loyalty-claim
                    data-reward-id="{{ $reward['id'] }}"
                    @disabled(! $reward['can_claim'])
                >
                    {{ $reward['can_claim'] ? 'دریافت جایزه' : 'امتیاز ناکافی' }}
                </button>
            </article>
        @empty
            <article class="portal-unit-card">
                <h4>جایزه فعالی وجود ندارد</h4>
                <p>جوایز فعال ساختمان در این بخش نمایش داده می‌شوند.</p>
            </article>
        @endforelse
    </div>

    @if (data_get($portalData, 'loyalty.claims', []))
        <div class="portal-detail-timeline mt-4">
            @foreach (data_get($portalData, 'loyalty.claims', []) as $claim)
                <article>
                    <span class="portal-detail-timeline__dot"></span>
                    <div>
                        <strong>{{ $claim['title'] }}</strong>
                        <p>
                            {{ number_format($claim['points']) }} امتیاز
                            •
                            {{ $statusLabel($claim['status']) }}
                        </p>
                        <time>{{ $claim['claimed_at_jalali'] ?: '—' }}</time>
                    </div>
                </article>
            @endforeach
        </div>
    @endif
</section>


</div>
@include('portal.resident.forms', ['resource'=>'loyalty'])
@endsection
