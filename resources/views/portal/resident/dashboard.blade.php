@extends('portal.layouts.app')
@section('title', 'خانه من | Buildino')
@section('page-title', 'خانه من')
@section('content')
@php
    $money = static fn ($value) => strtr(number_format((int) ($value ?? 0)), array_combine(range(0,9), ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹']));
    $presenter = app(\App\Services\Web\WebDataTablePresenter::class);
    $units = collect($portalData['units']);
    $stats = $portalData['stats'];
    $currencies = $units->map(fn ($unit) => data_get($unit, 'wallet.currency', 'IRR'))->unique();
    $currency = ['IRR'=>'ریال', 'IRT'=>'تومان'][$currencies->first() ?? 'IRR'] ?? $currencies->first();
    $activity = collect();
    foreach (['reservations'=>'رزرو امکانات', 'guest_visits'=>'مهمان', 'service_requests'=>'خدمت', 'support_tickets'=>'پشتیبانی'] as $key=>$label) {
        $resourceKey = ['reservations'=>'reservations','guest_visits'=>'guests','service_requests'=>'services','support_tickets'=>'support'][$key];
        foreach ($portalData[$key]->take(3) as $record) {
            $activity->push(['label'=>$label, 'resource'=>$resourceKey, 'record'=>$record,
                'title'=>($record->subject ?? $record->title ?? $record->buildingFacility?->title ?? trim(($record->guest?->first_name ?? '').' '.($record->guest?->last_name ?? ''))) ?: $label]);
        }
    }
    $activity = $activity->sortByDesc(fn ($item) => $item['record']->created_at)->take(6);
@endphp
<div class="resident-home" data-resident-dashboard>
    <header class="resident-welcome"><div><span class="portal-eyebrow">خانه و امور ساختمان</span><h2>سلام {{ auth('web')->user()->first_name ?: 'کاربر بیلدینو' }}</h2><p>{{ $units->isEmpty() ? 'برای شروع، دعوت واحد خود را بپذیرید.' : 'حساب‌ها و درخواست‌های واحدهای خود را پیگیری کنید.' }}</p></div><div class="resident-welcome__icon" aria-hidden="true">@include('management.partials.icon', ['name'=>'home','size'=>42])</div></header>
    @if($units->isEmpty())
        <section class="resident-panel resident-empty"><h3>هنوز به واحدی متصل نشده‌اید</h3><p>کد دعوت را از مدیر ساختمان دریافت کنید.</p><button type="button" class="portal-primary-button" data-invitation-open>واردکردن کد دعوت</button></section>
    @else
        <section class="resident-account" aria-label="خلاصه حساب واحدها">
            <article class="resident-account__payable"><div><span>مانده قابل پرداخت صورتحساب‌ها</span><strong>{{ $currencies->count() > 1 ? 'چند واحد پولی' : $money($stats['outstanding_total']) }} @if($currencies->count() === 1)<small>{{ $currency }}</small>@endif</strong>@if($stats['credit_remaining_total'] ?? 0)<p>بستانکاری واحدها: {{ $currencies->count() > 1 ? 'در جزئیات هر واحد' : $money($stats['credit_remaining_total']).' '.$currency }}</p>@endif</div><a class="portal-primary-button" href="{{ route('portal.resident.operations.index', ['resource'=>'invoices']) }}">مشاهده صورتحساب‌ها</a></article>
            <article><span>اعتبار قابل استفاده واحدها</span><strong>{{ $currencies->count() > 1 ? 'تفکیک‌شده در هر واحد' : $money($stats['unit_wallet_available']) }} @if($currencies->count() === 1)<small>{{ $currency }}</small>@endif</strong><a href="{{ route('portal.resident.operations.index', ['resource'=>'units']) }}">حساب واحدها</a></article>
        </section>
        <section class="resident-panel" aria-labelledby="resident-shortcuts"><div class="resident-section-heading"><h3 id="resident-shortcuts">کارهای من</h3><span>{{ $money($units->count()) }} واحد مرتبط</span></div><div class="resident-shortcuts">
            @foreach(['invoices'=>['invoice','صورتحساب‌ها'], 'guests'=>['users','مهمان جدید'], 'reservations'=>['calendar','رزرو امکانات'], 'services'=>['tools','درخواست خدمت'], 'support'=>['support','پشتیبانی'], 'units'=>['building','واحدهای من']] as $key=>[$icon,$label])
                <a href="{{ route('portal.resident.operations.index', ['resource'=>$key]) }}">@include('management.partials.icon', ['name'=>$icon,'size'=>23])<strong>{{ $label }}</strong>@include('management.partials.icon', ['name'=>'chevron','size'=>16])</a>
            @endforeach
        </div></section>
        <section class="resident-panel" aria-labelledby="resident-activity-title"><div class="resident-section-heading"><h3 id="resident-activity-title">آخرین درخواست‌ها</h3></div><div class="resident-activity">
            @forelse($activity as $item)
                <a class="resident-activity__item" href="{{ route('portal.resident.operations.show', ['resource'=>$item['resource'],'id'=>$item['record']->id]) }}"><div><small>{{ $item['label'] }}</small><strong>{{ $item['title'] }}</strong><span>{{ $item['record']->unit?->title ?: 'واحد '.$money($item['record']->unit?->unit_number) }}</span></div><span class="portal-status portal-status--{{ $presenter->statusTone($item['record']->status) }}">{{ $presenter->statusLabel($item['record']->status) }}</span>@include('management.partials.icon', ['name'=>'chevron','size'=>17])</a>
            @empty
                <div class="resident-empty"><p>درخواستی ثبت نکرده‌اید.</p><a class="portal-action-button" href="{{ route('portal.resident.operations.index', ['resource'=>'support']) }}">ارتباط با پشتیبانی</a></div>
            @endforelse
        </div></section>
    @endif
    <div class="resident-secondary-links"><a href="{{ route('portal.resident.operations.index', ['resource'=>'wallet']) }}">گردش کیف پول</a><a href="{{ route('portal.resident.operations.index', ['resource'=>'loyalty']) }}">باشگاه وفاداری</a></div>
</div>
@endsection