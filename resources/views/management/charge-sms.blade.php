@extends('management.layouts.app')
@section('title','ارسال پیامک شارژ دوره')
@section('page-title','ارسال پیامک شارژ دوره')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/buildino-unit-ownerships.css') }}?v={{ filemtime(public_path('css/buildino-unit-ownerships.css')) }}">
<link rel="stylesheet" href="{{ asset('css/buildino-charge-sms.css') }}?v={{ filemtime(public_path('css/buildino-charge-sms.css')) }}">
@endpush
@section('content')
@php
    $digits = ['0'=>'۰','1'=>'۱','2'=>'۲','3'=>'۳','4'=>'۴','5'=>'۵','6'=>'۶','7'=>'۷','8'=>'۸','9'=>'۹',','=>'٬'];
    $number = fn ($value) => strtr(number_format((int) $value), $digits);
    $sendLabels = ['accepted'=>'پذیرفته‌شده توسط قاصدک','processing'=>'در حال ارسال / نیازمند بررسی','unknown'=>'نتیجه نامشخص؛ بررسی در قاصدک'];
@endphp
<div class="ownership-page sms-page" data-charge-sms-page>
    @include('management.partials.finance-navigation', ['currentResource'=>'charge-sms'])
    <header class="ownership-hero"><div class="ownership-hero__icon">@include('management.partials.icon', ['name'=>'message','size'=>26])</div><div><h2>ارسال پیامک شارژ دوره</h2><p>انتخاب دوره، بررسی مخاطبان و ارسال پیامک</p></div></header>
    @if(session('status'))<p class="ownership-alert ownership-alert--success" role="status">{{ session('status') }}</p>@endif
    @if($errors->any())<p class="ownership-alert ownership-alert--error" role="alert">{{ $errors->first() }}</p>@endif
    @if(!$ready)<p class="ownership-alert" role="status">ارسال تا تنظیم قالب تأییدشده پیامک شارژ در قاصدک غیرفعال است.</p>@endif
    <section class="ownership-card" aria-labelledby="sms-period-title">
        <header class="ownership-card__heading"><h3 id="sms-period-title">ساختمان و دوره</h3></header>
        <form method="GET" class="sms-location" data-sms-location>
            @if($buildings->count() !== 1)<label class="ownership-field"><span>ساختمان</span><select name="building_id" onchange="this.form.querySelector('[name=period_id]').value='';this.form.submit()"><option value="">انتخاب ساختمان</option>@foreach($buildings as $item)<option value="{{ $item->id }}" @selected($building?->id === $item->id)>{{ $item->title }}</option>@endforeach</select></label>@else<input type="hidden" name="building_id" value="{{ $building?->id }}"><div class="sms-building"><small>ساختمان</small><strong>{{ $building?->title }}</strong></div>@endif
            <label class="ownership-field"><span>دوره شارژ</span><select name="period_id" @disabled(!$building || $periods->isEmpty())><option value="">انتخاب دوره</option>@foreach($periods as $item)<option value="{{ $item->id }}" @selected($period?->id === $item->id)>{{ $item->title }}</option>@endforeach</select></label>
            <button type="submit" class="ownership-button ownership-button--primary" @disabled(!$building || $periods->isEmpty())>نمایش مخاطبان</button>
        </form>
        @if($building && $periods->isEmpty())<p class="ownership-empty">این ساختمان دوره‌ای با صورتحساب صادرشده ندارد.</p>@endif
    </section>
    <details class="sms-guide"><summary>مبنای مبلغ و انتخاب مخاطب</summary><p>مبلغ محاسبه‌شده دوره، پس از اعمال بستانکاری و پیش از کسر پرداخت‌هاست؛ لزوماً مانده فعلی بدهی نیست. مبالغ پیامک به ریال نمایش داده می‌شوند. مخاطب از سوابق سکونت همان دوره تعیین می‌شود و هر شخص در هر ساختمان و دوره یک بار امکان ارسال دارد.</p></details>
    @if($period)
        @if($recipients['unresolved'])<p class="ownership-alert" role="alert">سابقه ساکن اصلی در این دوره برای واحدهای {{ strtr(collect($recipients['unresolved'])->pluck('unit_number')->implode('، '), $digits) }} ناقص یا مبهم است؛ سوابق سکونت را بررسی کنید.</p>@endif
        <section class="ownership-card" id="charge-sms-panel" data-send-url="{{ route('management.charge-sms.send',$period) }}" data-period-label="{{ $building->title.' · '.$period->title }}" aria-labelledby="sms-recipients-title">
            <header class="ownership-card__heading ownership-card__heading--split"><div><h3 id="sms-recipients-title">مخاطبان {{ $period->title }}</h3><span class="sms-muted">{{ $number(count($recipients['rows'])) }} مخاطب</span></div><span class="sms-badge" id="charge-selected-count" role="status" aria-live="polite">۰ مخاطب انتخاب شده</span></header>
            <label class="sms-select-all"><input type="checkbox" id="charge-select-all"> انتخاب همه مخاطبان قابل ارسال</label>
            <div class="sms-recipients">
                @forelse($recipients['rows'] as $row)
                @php($recipientName = trim(($row['user']?->first_name ?? '').' '.($row['user']?->last_name ?? '')) ?: 'کاربر بدون نام')
                <article class="sms-recipient" data-recipient="{{ $row['user_id'] }}">
                    <label class="sms-recipient__identity"><input type="checkbox" class="charge-recipient" value="{{ $row['user_id'] }}" aria-label="انتخاب {{ $recipientName }}" @disabled(!$ready || $recipients['unresolved'] || !$row['eligible'] || $row['notice'])><span><strong>{{ $recipientName }}</strong><bdi>{{ strtr($row['user']?->mobile ?? '—', $digits) }}</bdi></span></label>
                    <div class="sms-recipient__units"><small>واحدها</small><span>{{ strtr(implode('، ',$row['units']), $digits) }}</span></div>
                    <div class="sms-recipient__amount"><small>مبلغ محاسبه‌شده دوره</small><strong>{{ $number($row['amount']) }} <small>ریال</small></strong></div>
                    <span class="sms-recipient__status" data-send-status>{{ $row['notice'] ? ($sendLabels[$row['notice']->status] ?? 'قبلاً درخواست ارسال ثبت شده') : ($row['eligible'] ? 'آماده ارسال' : 'حساب یا موبایل نامعتبر') }}</span>
                </article>
                @empty<p class="ownership-empty">مخاطبی با مبلغ محاسبه‌شده مثبت وجود ندارد.</p>@endforelse
            </div>
            <div class="sms-send-footer"><p id="charge-result" role="status" aria-live="polite"></p><button type="button" class="ownership-button ownership-button--primary" id="charge-send" disabled>ارسال پیامک به انتخاب‌شده‌ها</button></div>
            <progress class="sms-progress" id="charge-progress" value="0" max="1" hidden aria-label="پیشرفت ارسال پیامک"></progress>
            <dialog class="sms-confirm" id="charge-confirm" aria-labelledby="charge-confirm-title"><h3 id="charge-confirm-title">تأیید ارسال پیامک</h3><p>{{ $building->title }} · {{ $period->title }}</p><p data-sms-confirm-count></p><div class="sms-confirm__actions"><button type="button" class="ownership-button" data-sms-cancel>انصراف</button><button type="button" class="ownership-button ownership-button--primary" data-sms-confirm>تأیید و ارسال</button></div></dialog>
        </section>
        <section class="ownership-card" aria-labelledby="sms-history-title"><header class="ownership-card__heading ownership-card__heading--split"><div><h3 id="sms-history-title">سوابق ارسال دوره</h3><span class="sms-muted">{{ $number($history->count()) }} درخواست ثبت‌شده</span></div><a class="ownership-button" href="{{ request()->fullUrl() }}">به‌روزرسانی سوابق</a></header>
            <div class="sms-history">@forelse($history as $notice)<article><div><strong>{{ $notice->user?->first_name }} {{ $notice->user?->last_name }}</strong><bdi>{{ strtr($notice->mobile, $digits) }}</bdi></div><div><small>مبلغ زمان ارسال</small><strong>{{ $number($notice->amount) }} {{ $notice->currency === 'IRT' ? 'تومان' : 'ریال' }}</strong></div><div><small>تاریخ درخواست</small><time>{{ strtr(\Morilog\Jalali\Jalalian::fromCarbon($notice->created_at)->format('Y/m/d H:i'), $digits) }}</time></div><div><small>تاریخ پذیرش</small><time>{{ $notice->sent_at ? strtr(\Morilog\Jalali\Jalalian::fromCarbon($notice->sent_at)->format('Y/m/d H:i'), $digits) : '—' }}</time></div><span class="sms-recipient__status">{{ $sendLabels[$notice->status] ?? 'نیازمند بررسی' }}</span></article>@empty<p class="ownership-empty">ارسالی ثبت نشده است.</p>@endforelse</div>
            <details class="sms-guide"><summary>وضعیت ارسال چه معنایی دارد؟</summary><p>پذیرش توسط قاصدک به معنی تحویل قطعی به گوشی نیست. درخواست با نتیجه نامشخص یا در حال پردازش، برای جلوگیری از پیام تکراری خودکار تکرار نمی‌شود.</p></details>
        </section>
    @else<section class="ownership-card ownership-empty">{{ $building ? 'دوره شارژ را انتخاب کنید تا مخاطبان و سوابق آن نمایش داده شوند.' : 'ابتدا ساختمان و سپس دوره شارژ را انتخاب کنید.' }}</section>@endif
</div>
@endsection
@push('scripts')
<script src="{{ asset('js/buildino-charge-sms.js') }}?v={{ filemtime(public_path('js/buildino-charge-sms.js')) }}" defer></script>
@endpush
