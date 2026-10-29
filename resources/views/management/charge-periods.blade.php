@extends('management.layouts.app')
@section('title', 'دوره‌های شارژ | Buildino')
@section('page-title', 'دوره‌های شارژ')
@section('page-subtitle', 'تعریف بازه شارژ ساختمان، محاسبه و صدور صورتحساب واحدها')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/buildino-unit-ownerships.css') }}?v={{ filemtime(public_path('css/buildino-unit-ownerships.css')) }}">
<link rel="stylesheet" href="{{ asset('css/buildino-charge-periods.css') }}?v={{ filemtime(public_path('css/buildino-charge-periods.css')) }}">
@endpush
@section('content')
<div class="ownership-page period-page" data-period-page data-core-process-ui>
    @include('management.partials.finance-navigation', ['currentResource' => 'charge-periods'])
    <header class="ownership-hero"><div class="ownership-hero__icon">@include('management.partials.icon', ['name' => 'wallet', 'size' => 27])</div><div><span class="eyebrow">مدیریت مالی ساختمان</span><h2>شارژ هر دوره، قدم‌به‌قدم</h2><p>بازه دوره را مشخص کنید، شارژ را محاسبه و سپس صورتحساب‌ها را صادر کنید.</p></div></header>
    <div class="ownership-alert ownership-alert--error" data-period-error role="alert" tabindex="-1" hidden></div>
    <div class="ownership-alert ownership-alert--success" data-period-success role="status" aria-live="polite" hidden></div>

    <section class="ownership-card" data-period-location aria-labelledby="period-location-title">
        <div class="ownership-card__heading"><div><h3 id="period-location-title" tabindex="-1">دوره‌های کدام ساختمان؟</h3><p>ساختمان را انتخاب کنید تا دوره‌ها و عملیات در دسترس آن را ببینید.</p></div></div>
        <label class="ownership-field"><span>ساختمان</span><select data-period-building><option value="">انتخاب ساختمان</option>@foreach ($periodConfig['buildings'] as $building)<option value="{{ $building['id'] }}">{{ $building['title'] }}</option>@endforeach</select></label>
        @if ($periodConfig['buildings']->isEmpty())<p class="ownership-empty">ساختمان قابل دسترسی برای مشاهده دوره‌های شارژ وجود ندارد.</p>@endif
        <div class="ownership-actions"><button type="button" class="ownership-button ownership-button--primary" data-period-resume hidden>نمایش دوره‌های ساختمان</button></div>
    </section>

    <section class="ownership-card" data-period-directory hidden aria-labelledby="period-directory-title">
        <div class="ownership-card__heading ownership-card__heading--split"><div><h3 id="period-directory-title" tabindex="-1">دوره‌های شارژ</h3><p data-period-building-label></p></div><div class="period-toolbar"><button type="button" class="ownership-button" data-period-change-building>تغییر ساختمان</button><button type="button" class="ownership-button" data-period-reload>به‌روزرسانی</button><button type="button" class="ownership-button ownership-button--primary" data-period-new hidden>+ دوره جدید</button></div></div>
        <div class="period-guide"><strong>از تعریف دوره تا صورتحساب</strong><p>ایجاد دوره، بدهی صادر نمی‌کند. ابتدا محاسبه را انجام دهید؛ صدور صورتحساب یک اقدام جداگانه است.</p><a class="period-link" href="{{ route('management.operations.show', 'charge-formulas') }}" data-period-formulas hidden>بررسی فرمول‌های شارژ</a></div>
        <div class="ownership-form-grid period-filters">
            <label class="ownership-field"><span>جست‌وجو در همین صفحه</span><input type="search" data-period-search placeholder="عنوان دوره، مثلاً شارژ مهر"></label>
            <label class="ownership-field"><span>وضعیت در همین صفحه</span><select data-period-filter><option value="">همه وضعیت‌ها</option><option value="draft">پیش‌نویس</option><option value="calculating">در حال محاسبه</option><option value="calculated">محاسبه‌شده</option><option value="issued">صادرشده</option><option value="closed">بسته‌شده</option><option value="cancelled">لغوشده</option></select></label>
        </div>
        <p class="ownership-location-hint" data-period-list-status role="status" aria-live="polite"></p>
        <div class="ownership-record-list period-list" data-period-list></div>
        <nav class="ownership-actions period-pagination" aria-label="صفحه‌بندی دوره‌های شارژ"><button type="button" class="ownership-button" data-period-prev disabled>صفحه قبل</button><span data-period-pagination></span><button type="button" class="ownership-button" data-period-next disabled>صفحه بعد</button></nav>
    </section>

    <section class="ownership-card" data-period-editor hidden aria-labelledby="period-editor-title">
        <div class="ownership-card__heading"><div><h3 id="period-editor-title" data-period-editor-title tabindex="-1">تعریف دوره جدید</h3><p data-period-editor-context></p></div></div>
        <p class="period-description">نام و بازه دوره را وارد کنید. تاریخ سررسید، آخرین مهلت پرداخت صورتحساب‌های این دوره است.</p>
        <form data-period-form novalidate>
            <fieldset class="period-fields" data-period-fields><div class="ownership-form-grid">
                <label class="ownership-field ownership-field--wide"><span>عنوان دوره *</span><input name="title" required maxlength="255" placeholder="مثلاً شارژ مهر ۱۴۰۵" aria-describedby="period-title-error"><small class="period-field-error" id="period-title-error" data-period-field-error="title" hidden></small></label>
                <label class="ownership-field"><span>شروع دوره *</span><input name="period_start" type="date" required aria-describedby="period-period_start-error"><small class="period-field-error" id="period-period_start-error" data-period-field-error="period_start" hidden></small></label>
                <label class="ownership-field"><span>پایان دوره *</span><input name="period_end" type="date" required aria-describedby="period-period_end-error"><small class="period-field-error" id="period-period_end-error" data-period-field-error="period_end" hidden></small></label>
                <label class="ownership-field"><span>سررسید پرداخت *</span><input name="due_date" type="date" required aria-describedby="period-due_date-error"><small>هم‌زمان با پایان دوره یا پس از آن</small><small class="period-field-error" id="period-due_date-error" data-period-field-error="due_date" hidden></small></label>
            </div></fieldset>
            <div class="ownership-editor__actions"><button type="button" class="ownership-button" data-period-cancel>انصراف و بازگشت</button><button type="submit" class="ownership-button ownership-button--primary" data-period-save>ذخیره دوره و ادامه</button></div>
        </form>
    </section>

    <section class="ownership-card" data-period-detail hidden aria-labelledby="period-detail-title">
        <div class="ownership-card__heading ownership-card__heading--split"><div><h3 id="period-detail-title" data-period-detail-title tabindex="-1">جزئیات دوره</h3><p data-period-detail-context></p></div><div class="period-toolbar"><button type="button" class="ownership-button" data-period-detail-reload>به‌روزرسانی وضعیت</button><button type="button" class="ownership-button" data-period-close>بازگشت به دوره‌ها</button></div></div>
        <p class="ownership-location-hint" data-period-detail-status role="status" aria-live="polite"></p>
        <div data-period-detail-body hidden>
            <div class="ownership-flow" aria-label="مراحل دوره شارژ">
                <span data-period-stage="0"><b>۱</b> تعریف دوره</span><span data-period-stage="1"><b>۲</b> محاسبه شارژ</span><span data-period-stage="2"><b>۳</b> صدور صورتحساب</span>
            </div>
            <div class="period-overview"><span class="period-badge" data-period-status></span><dl class="period-dates"><div><dt>شروع دوره</dt><dd data-period-start></dd></div><div><dt>پایان دوره</dt><dd data-period-end></dd></div><div><dt>سررسید پرداخت</dt><dd data-period-due></dd></div></dl></div>
            <div class="period-metrics"><div><span>محاسبات پایه شارژ</span><strong data-period-calculations>—</strong></div><div><span>سهم‌های تخصیص هزینه</span><strong data-period-allocations>—</strong></div><div><span>صورتحساب‌های دوره</span><strong data-period-invoices>—</strong></div></div>
            <div class="period-guide"><h4 data-period-next-title></h4><p data-period-next-hint></p></div>
            <div class="ownership-actions" data-period-detail-actions>
                <button type="button" class="ownership-button" data-period-edit hidden>ویرایش دوره</button>
                <button type="button" class="ownership-button ownership-button--primary" data-period-calculate hidden>محاسبه شارژ</button>
                <button type="button" class="ownership-button ownership-button--primary" data-period-issue hidden>صدور صورتحساب‌ها</button>
                <a class="period-link" href="{{ route('management.operations.show', 'invoices') }}" data-period-invoice-link hidden>رفتن به صورتحساب‌ها</a>
            </div>
            <div class="period-confirm" data-period-confirm hidden role="region" aria-labelledby="period-confirm-title">
                <h4 id="period-confirm-title" tabindex="-1" data-period-confirm-title></h4><p data-period-confirm-copy></p>
                <div class="ownership-editor__actions"><button type="button" class="ownership-button" data-period-action-cancel>بازگشت بدون اجرا</button><button type="button" class="ownership-button ownership-button--primary" data-period-action-submit>تأیید و اجرا</button></div>
            </div>
        </div>
        <button type="button" class="ownership-button" data-period-detail-retry hidden>تلاش مجدد</button>
    </section>
    <noscript><p class="ownership-alert ownership-alert--error">برای مدیریت دوره‌های شارژ، جاوااسکریپت مرورگر را فعال کنید.</p></noscript>
</div>
@endsection
@push('scripts')
<script>window.BuildinoPeriodConfig = {{ \Illuminate\Support\Js::from($periodConfig) }};</script>
<script src="{{ asset('js/buildino-charge-periods.js') }}?v={{ filemtime(public_path('js/buildino-charge-periods.js')) }}" defer></script>
@endpush
