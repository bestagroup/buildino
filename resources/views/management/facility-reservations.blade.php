@extends('management.layouts.app')
@section('title', 'رزرو امکانات | Buildino')
@section('page-title', 'رزرو امکانات ساختمان')
@section('page-subtitle', 'سانس آزاد را پیدا کنید، قوانین را ببینید و رزرو خود را پیگیری کنید.')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/buildino-unit-ownerships.css') }}?v={{ filemtime(public_path('css/buildino-unit-ownerships.css')) }}">
<link rel="stylesheet" href="{{ asset('css/buildino-facilities.css') }}?v={{ filemtime(public_path('css/buildino-facilities.css')) }}">
<link rel="stylesheet" href="{{ asset('css/buildino-reservations.css') }}?v={{ filemtime(public_path('css/buildino-reservations.css')) }}">
@endpush
@section('content')
<div class="ownership-page reservation-page" data-reservation-page data-core-process-ui>
    <header class="ownership-hero"><div class="ownership-hero__icon">@include('management.partials.icon', ['name' => 'building', 'size' => 27])</div><div><span class="eyebrow">رزرو امکانات · P11</span><h2>زمانی برای استفاده از امکانات ساختمان</h2><p>ظرفیت و قوانین در زمان ثبت رزرو دوباره بررسی می‌شوند.</p></div></header>
    <div class="ownership-alert ownership-alert--error" data-reservation-error role="alert" tabindex="-1" hidden></div>
    <div class="ownership-alert ownership-alert--success" data-reservation-success role="status" aria-live="polite" hidden></div>
    <section class="ownership-card"><label class="ownership-field"><span>ساختمان</span><select data-reservation-building><option value="">انتخاب ساختمان</option>@foreach ($reservationConfig['buildings'] as $building)<option value="{{ $building['id'] }}">{{ $building['title'] }}</option>@endforeach</select></label>@if ($reservationConfig['buildings']->isEmpty())<p class="ownership-empty">ساختمانی با دسترسی مشاهده رزروها وجود ندارد.</p>@endif</section>
    <section class="ownership-card" data-reservation-booking hidden aria-labelledby="reservation-booking-title">
        <div class="ownership-card__heading"><div><h3 id="reservation-booking-title" data-reservation-booking-title tabindex="-1">رزرو جدید</h3><p data-reservation-choice-label>یک امکان فعال را انتخاب کنید.</p></div></div>
        <div class="ownership-alert ownership-alert--error" data-reservation-booking-error role="alert" tabindex="-1" hidden></div>
        <div class="ownership-alert ownership-alert--success" data-reservation-booking-success role="status" hidden></div>
        <p class="reservation-note" data-reservation-no-units hidden>برای این ساختمان واحدی جهت رزرو وجود ندارد. پیش از ثبت رزرو، ساختار واحدهای ساختمان باید تکمیل شود.</p>
        <div class="ownership-flow" aria-label="مراحل رزرو">@foreach (['انتخاب امکان', 'تاریخ و سانس', 'بررسی و ثبت'] as $label)<span data-reservation-step-label="{{ $loop->index }}"><b>{{ $loop->iteration }}</b>{{ $label }}</span>@endforeach</div>
        <div class="reservation-note" data-reservation-uncertain hidden><p>پیش از ثبت دوباره، بررسی کنید درخواست قبلی در فهرست رزروها ثبت نشده باشد.</p><div class="ownership-editor__actions"><button type="button" class="ownership-button" data-reservation-check-history>بررسی رزروهای ثبت‌شده</button><button type="button" class="ownership-button" data-reservation-resume>بررسی کردم؛ شروع رزرو جدید</button></div></div>
        <div data-reservation-panel="0">
            <div class="ownership-form-grid"><label class="ownership-field"><span>جست‌وجوی امکان</span><input type="search" data-reservation-facility-search placeholder="عنوان یا کد امکان"></label><div class="ownership-editor__actions"><button type="button" class="ownership-button" data-reservation-facility-reload>به‌روزرسانی امکانات</button></div></div>
            <p class="ownership-form-hint" data-reservation-facility-state role="status" aria-live="polite"></p><div class="ownership-record-list reservation-facilities" data-reservation-facilities></div>
            <nav class="ownership-actions" aria-label="صفحه‌بندی امکانات"><button type="button" class="ownership-button" data-reservation-facility-prev disabled>قبلی</button><span data-reservation-facility-page></span><button type="button" class="ownership-button" data-reservation-facility-next disabled>بعدی</button></nav>
        </div>
        <div data-reservation-panel="1" hidden>
            <div class="ownership-record-list" data-reservation-facility-info></div>
            <details class="ownership-history"><summary>قوانین استفاده و لغو</summary><div class="ownership-record-list" data-reservation-rules></div></details>
            <div class="reservation-date-shortcuts" aria-label="انتخاب سریع تاریخ"><button type="button" class="ownership-button" data-reservation-day="0">امروز</button><button type="button" class="ownership-button" data-reservation-day="1">فردا</button><span>یا تاریخ دلخواه را از تقویم انتخاب کنید.</span></div>
            <form class="ownership-form-grid" data-reservation-availability-form><label class="ownership-field"><span>تاریخ مراجعه *</span><input type="date" name="date" required></label><div class="ownership-editor__actions"><button type="submit" class="ownership-button ownership-button--primary">نمایش سانس‌ها</button></div></form>
            <p class="ownership-form-hint" data-reservation-slot-state role="status" aria-live="polite"></p><div class="ownership-record-list reservation-slots" data-reservation-slots></div>
            <div class="ownership-editor__actions"><button type="button" class="ownership-button" data-reservation-back-facility>تغییر امکان</button></div>
        </div>
        <div data-reservation-panel="2" hidden>
            <div class="ownership-record-list" data-reservation-review></div>
            <form data-reservation-create-form>
                <div class="ownership-form-grid facility-block"><label class="ownership-field ownership-field--wide"><span>واحد رزروکننده *</span><select name="unit_id" required><option value="">واحد را انتخاب کنید</option></select><small data-reservation-unit-state></small></label><label class="ownership-field ownership-field--wide"><span>توضیحات (اختیاری)</span><textarea name="description" rows="2" maxlength="5000"></textarea></label></div>
                <p class="ownership-form-hint">این اقدام درخواست رزرو را ثبت می‌کند. قطعی‌بودن رزرو و نیاز به پرداخت یا تأیید بر اساس پاسخ سامانه مشخص می‌شود.</p>
                <div class="ownership-editor__actions"><button type="button" class="ownership-button" data-reservation-back-slots>تغییر سانس</button><button type="submit" class="ownership-button ownership-button--primary" data-reservation-create>بررسی قوانین و ثبت درخواست</button></div>
            </form>
        </div>
    </section>
    <section class="ownership-card" data-reservation-history hidden>
        <div class="ownership-card__heading ownership-card__heading--split"><div><h3>رزروهای ساختمان</h3><p>وضعیت رزروها، پرداخت‌ها و لغوها را پیگیری کنید.</p></div><button type="button" class="ownership-button" data-reservation-history-reload>به‌روزرسانی</button></div>
        <label class="ownership-field"><span>وضعیت رزرو</span><select data-reservation-status><option value="">همه وضعیت‌ها</option>@foreach (['pending' => 'در انتظار تأیید', 'payment_pending' => 'در انتظار پرداخت', 'approved' => 'تأییدشده', 'confirmed' => 'قطعی', 'rejected' => 'ردشده', 'cancelled' => 'لغوشده', 'completed' => 'پایان‌یافته', 'expired' => 'منقضی‌شده'] as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select></label>
        <p class="ownership-form-hint" data-reservation-history-state role="status"></p><div class="ownership-record-list" data-reservation-history-list></div>
        <nav class="ownership-actions" aria-label="صفحه‌بندی رزروها"><button type="button" class="ownership-button" data-reservation-history-prev disabled>قبلی</button><span data-reservation-history-page></span><button type="button" class="ownership-button" data-reservation-history-next disabled>بعدی</button></nav>
    </section>
    <section class="ownership-card" data-reservation-detail hidden aria-labelledby="reservation-detail-title">
        <div class="ownership-card__heading ownership-card__heading--split"><div><h3 id="reservation-detail-title" data-reservation-detail-title tabindex="-1">جزئیات رزرو</h3><p data-reservation-detail-state role="status"></p></div><button type="button" class="ownership-button" data-reservation-detail-reload>به‌روزرسانی وضعیت</button><button type="button" class="ownership-button" data-reservation-detail-close>بستن جزئیات</button></div>
        <div class="ownership-alert ownership-alert--error" data-reservation-detail-error role="alert" tabindex="-1" hidden></div>
        <div class="ownership-alert ownership-alert--success" data-reservation-detail-success role="status" hidden></div>
        <div class="ownership-record-list" data-reservation-detail-body></div>
        <div class="ownership-actions" data-reservation-actions></div>
        <form class="facility-block" data-reservation-pay-form hidden><h4>پرداخت از کیف پول</h4><p class="ownership-form-hint" data-reservation-pay-amount></p><label class="ownership-field"><span>منبع پرداخت</span><select name="payer_source" required><option value="user_wallet">کیف پول شخصی</option><option value="unit_wallet">کیف پول واحد رزروکننده</option></select></label><p class="ownership-form-hint">با تأیید، مبلغ از کیف پول انتخاب‌شده برداشت می‌شود. وضعیت بعد از پرداخت ممکن است همچنان در انتظار تأیید مدیر باشد.</p><div class="ownership-editor__actions"><button type="button" class="ownership-button" data-reservation-pay-close>انصراف</button><button type="submit" class="ownership-button ownership-button--primary">تأیید و پرداخت</button></div></form>
        <form class="facility-block" data-reservation-cancel-form hidden><h4>لغو رزرو</h4><p class="ownership-form-hint" data-reservation-cancel-terms></p><label class="ownership-field"><span>دلیل لغو (اختیاری)</span><textarea name="reason" rows="2" maxlength="5000"></textarea></label><div class="ownership-editor__actions"><button type="button" class="ownership-button" data-reservation-cancel-close>انصراف</button><button type="submit" class="ownership-button ownership-button--danger">تأیید لغو رزرو</button></div></form>
        <form class="facility-block" data-reservation-decision-form hidden><h4 data-reservation-decision-title></h4><p class="ownership-form-hint">این اقدام روی وضعیت رزرو اعمال می‌شود.</p><div class="ownership-editor__actions"><button type="button" class="ownership-button" data-reservation-decision-close>انصراف</button><button type="submit" class="ownership-button ownership-button--primary">تأیید اقدام</button></div></form>
    </section>
    <noscript><p class="ownership-alert ownership-alert--error">برای رزرو امکانات، جاوااسکریپت مرورگر را فعال کنید.</p></noscript>
</div>
@endsection
@push('scripts')
<script>window.BuildinoReservationConfig = {{ \Illuminate\Support\Js::from($reservationConfig) }};</script>
<script src="{{ asset('js/buildino-reservations.js') }}?v={{ filemtime(public_path('js/buildino-reservations.js')) }}" defer></script>
@endpush
