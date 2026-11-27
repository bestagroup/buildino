@extends('portal.layouts.app')
@section('title', 'پنل ارائه‌دهنده خدمات | Buildino')
@section('page-title', 'پنل ارائه‌دهنده خدمات')
@section('content')
@php
    $digits = ['0'=>'۰','1'=>'۱','2'=>'۲','3'=>'۳','4'=>'۴','5'=>'۵','6'=>'۶','7'=>'۷','8'=>'۸','9'=>'۹',','=>'٬','-'=>'−'];
    $money = fn ($value) => strtr(number_format((int) $value), $digits);
    $stats = $portalData['stats'];
    $wallet = $portalData['personal_wallet'];
    $presenter = app(\App\Services\Web\WebDataTablePresenter::class);
    $verified = $portalData['bank_accounts']->where('is_verified',true);
@endphp
<div class="provider-workspace" data-provider-workspace>
    <header class="provider-heading"><div><h2>پنل ارائه‌دهنده خدمات</h2><p>کارها، درآمد و تسویه حساب</p></div><span class="provider-heading__icon" aria-hidden="true">@include('management.partials.icon',['name'=>'tools','size'=>32])</span></header>
    <section class="provider-account" aria-label="کیف پول ارائه‌دهنده">
        <div><span>موجودی قابل برداشت</span><strong>{{ $money($wallet['available_balance']) }} <small>ریال</small></strong><small>مبلغ مسدودشده: {{ $money($wallet['locked_balance']) }} ریال</small></div>
        <div class="provider-actions"><button type="button" class="portal-action-button" data-bs-toggle="modal" data-bs-target="#bankAccountModal">حساب بانکی جدید</button><button type="button" class="portal-primary-button" data-bs-toggle="modal" data-bs-target="#payoutModal" @disabled($verified->isEmpty() || $wallet['available_balance'] < 1)>درخواست تسویه</button></div>
        @if($verified->isEmpty())<p class="provider-account__help">برای درخواست تسویه، یک حساب بانکی تأییدشده نیاز دارید.</p>@elseif($wallet['available_balance'] < 1)<p class="provider-account__help">در حال حاضر موجودی قابل برداشت ندارید.</p>@endif
    </section>
    <section class="provider-stats" aria-label="خلاصه فعالیت">
        @foreach([['کارهای فعال',$stats['active_jobs'],'کار'],['کارهای تکمیل‌شده',$stats['completed_jobs'],'کار'],['درآمد تسویه‌شده',$stats['settled_earnings'],'ریال'],['درخواست‌های تسویه باز',$stats['pending_payout'],'ریال']] as [$label,$amount,$suffix])<article><span>{{ $label }}</span><strong>{{ $money($amount) }} <small>{{ $suffix }}</small></strong></article>@endforeach
    </section>
    <section class="provider-panel" aria-labelledby="provider-jobs-title"><header class="provider-section-heading"><h3 id="provider-jobs-title">کارهای اخیر</h3><a class="portal-action-button" href="{{ route('portal.provider.operations.index',['resource'=>'services']) }}">همه کارهای من</a></header>
        <div class="provider-jobs">@forelse($portalData['service_requests'] as $service)
            @php
                $state = $service->status->value;
                $payment = $service->walletPayment?->status?->value;
                $quote = $service->quotes->first();
            @endphp
            <article class="provider-job"><header><div><strong>{{ $service->title }}</strong><small>{{ $service->building?->title }} @if($service->unit) · واحد {{ strtr($service->unit->unit_number,$digits) }}@endif</small></div><span class="portal-status portal-status--{{ $presenter->statusTone($service->status) }}">{{ $presenter->statusLabel($service->status) }}</span></header>
                @if($service->description)<p>{{ \Illuminate\Support\Str::limit($service->description,140) }}</p>@endif
                @if($quote)<div class="provider-job__quote"><span>پیشنهاد شما</span><strong>{{ $money($quote->amount) }} {{ $service->building?->currency === 'IRT' ? 'تومان' : 'ریال' }}</strong><small>{{ $quote->status->value === 'accepted' ? 'پذیرفته‌شده' : $presenter->statusLabel($quote->status) }}</small></div>@endif
                <footer class="provider-actions"><a class="portal-action-button" href="{{ route('portal.provider.operations.show',['resource'=>'services','id'=>$service->id]) }}">جزئیات کار</a>
                    @if(in_array($state,['open','assigned'],true))<button type="button" class="portal-action-button" data-provider-quote data-service-id="{{ $service->id }}" data-service-title="{{ $service->title }}" data-service-currency="{{ $service->building?->currency ?: 'IRR' }}">پیشنهاد قیمت</button>@endif
                    @if($state === 'assigned' && $payment === 'locked')<button type="button" class="portal-primary-button" data-provider-action="start" data-service-id="{{ $service->id }}">شروع کار</button>@elseif($state === 'assigned')<span class="provider-muted">در انتظار پذیرش پیشنهاد و قفل مبلغ</span>@endif
                    @if($state === 'in_progress')<button type="button" class="portal-primary-button" data-provider-action="finish" data-service-id="{{ $service->id }}">اعلام پایان کار</button>@endif
                </footer>
            </article>
        @empty<div class="provider-empty">@include('management.partials.icon',['name'=>'tools','size'=>30])<h3>کاری به شما تخصیص داده نشده است.</h3></div>@endforelse</div>
    </section>
    <section class="provider-panel" aria-labelledby="provider-bank-list-title"><header class="provider-section-heading"><h3 id="provider-bank-list-title">حساب‌های بانکی</h3><button type="button" class="portal-action-button" data-bs-toggle="modal" data-bs-target="#bankAccountModal">افزودن حساب</button></header>
        <div class="provider-bank-list">@forelse($portalData['bank_accounts'] as $account)<article><div><strong>{{ $account->bank_name ?: 'حساب بانکی' }}</strong><bdi>{{ strtr($account->iban,$digits) }}</bdi></div><div class="provider-actions">@if($account->is_default)<span class="portal-status portal-status--info">پیش‌فرض</span>@endif<span class="portal-status portal-status--{{ $account->is_verified ? 'success' : 'warning' }}">{{ $account->is_verified ? 'تأییدشده' : 'در انتظار تأیید' }}</span></div></article>@empty<p class="provider-empty">حساب بانکی ثبت نکرده‌اید.</p>@endforelse</div>
    </section>
    <div class="provider-bottom-grid">
        <section class="provider-panel"><header class="provider-section-heading"><h3>گردش کیف پول</h3><a class="portal-action-button" href="{{ route('portal.provider.operations.index',['resource'=>'wallet']) }}">همه تراکنش‌ها</a></header><div class="provider-history">@forelse($wallet['entries'] as $entry)<article><div><strong>{{ $entry['description'] ?: ($entry['entry_type'] === 'credit' ? 'واریز' : 'برداشت') }}</strong><small>{{ strtr($entry['created_at_jalali'] ?: '—',$digits) }}</small></div><span>{{ $entry['entry_type'] === 'credit' ? '+' : '−' }}{{ $money($entry['amount']) }} ریال</span></article>@empty<p class="provider-empty">تراکنشی ثبت نشده است.</p>@endforelse</div></section>
        <section class="provider-panel"><header class="provider-section-heading"><h3>درخواست‌های تسویه</h3><a class="portal-action-button" href="{{ route('portal.provider.operations.index',['resource'=>'payouts']) }}">همه درخواست‌ها</a></header><div class="provider-history">@forelse($portalData['payouts'] as $payout)<article><div><a href="{{ route('portal.provider.operations.show',['resource'=>'payouts','id'=>$payout->id]) }}"><strong>{{ $money($payout->amount) }} ریال</strong></a><small>{{ strtr(\Morilog\Jalali\Jalalian::fromCarbon($payout->created_at)->format('Y/m/d H:i'),$digits) }}</small></div><span class="portal-status portal-status--{{ $presenter->statusTone($payout->status) }}">{{ $presenter->statusLabel($payout->status) }}</span></article>@empty<p class="provider-empty">درخواست تسویه‌ای ندارید.</p>@endforelse</div></section>
    </div>
</div>
@include('portal.provider.forms')
@endsection
