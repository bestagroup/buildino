@extends('management.layouts.app')
@section('title', 'مدیریت مهمان و مراجعه | Buildino')
@section('page-title', 'مدیریت مهمان و مراجعه')
@section('page-subtitle', 'ثبت مراجعه، مشاهده مجوز و پیگیری ورود و خروج مهمان‌ها')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/buildino-unit-ownerships.css') }}?v={{ filemtime(public_path('css/buildino-unit-ownerships.css')) }}">
@endpush
@section('content')
<div class="ownership-page" data-guest-page data-core-process-ui>
    <header class="ownership-hero"><div class="ownership-hero__icon">@include('management.partials.icon', ['name' => 'users', 'size' => 27])</div><div><span class="eyebrow">فرآیند P09</span><h2>مراجعه مهمان، از ثبت تا خروج</h2><p>واحد میزبان را انتخاب کنید و مراجعه‌های آن را پیگیری کنید.</p></div></header>
    <div class="ownership-alert ownership-alert--error" data-guest-error role="alert" hidden></div>
    <div class="ownership-alert ownership-alert--success" data-guest-success role="status" aria-live="polite" hidden></div>
    <section class="ownership-card" data-guest-location aria-labelledby="guest-location-title">
        <div class="ownership-card__heading"><span class="ownership-card__index">۱</span><div><h3 id="guest-location-title" tabindex="-1">واحد میزبان</h3><p>فقط محدوده قابل دسترسی شما نمایش داده می‌شود.</p></div></div>
        @if ($guestConfig['buildings']->isEmpty())
            <p class="ownership-empty">ساختمان قابل دسترسی برای مشاهده مراجعه‌ها وجود ندارد.</p>
        @else
            <div class="ownership-location-grid">
                <label class="ownership-field"><span>ساختمان</span><select data-guest-building><option value="">انتخاب ساختمان</option>@foreach ($guestConfig['buildings'] as $building)<option value="{{ $building['id'] }}">{{ $building['title'] }}</option>@endforeach</select></label>
                @foreach (['block' => 'بلوک', 'floor' => 'طبقه', 'unit' => 'واحد'] as $key => $label)
                <label class="ownership-field"><span>{{ $label }}</span><select data-guest-{{ $key }} disabled><option value="">ابتدا محدوده بالاتر را انتخاب کنید</option></select></label>
                @endforeach
            </div>
            <p class="ownership-location-hint" data-guest-location-status role="status"></p>
            <div class="ownership-actions"><button class="ownership-button ownership-button--primary" type="button" data-guest-location-continue hidden>نمایش مراجعه‌های واحد</button></div>
        @endif
    </section>
    <section class="ownership-card" data-guest-records hidden aria-labelledby="guest-records-title">
        <div class="ownership-card__heading ownership-card__heading--split"><div><h3 id="guest-records-title" tabindex="-1">مراجعه‌های واحد</h3><p data-guest-unit-label></p></div><button class="ownership-button" type="button" data-guest-change-unit>تغییر واحد</button><button class="ownership-button ownership-button--soft" type="button" data-guest-reload>به‌روزرسانی</button><button class="ownership-button ownership-button--primary" type="button" data-guest-add hidden>+ ثبت مراجعه</button></div>
        <div class="ownership-form-grid">
            <label class="ownership-field"><span>جست‌وجوی مهمان</span><input type="search" data-guest-search placeholder="نام، موبایل یا پلاک خودرو"></label>
            <label class="ownership-field"><span>وضعیت مراجعه</span><select data-guest-status><option value="">همه وضعیت‌ها</option><option value="invited">در انتظار مراجعه</option><option value="entered">داخل ساختمان</option><option value="exited">مراجعه پایان‌یافته</option><option value="expired">منقضی‌شده</option><option value="cancelled">لغوشده</option></select></label>
        </div>
        <p class="ownership-location-hint" data-guest-list-status role="status" aria-live="polite"></p>
        <div class="ownership-record-list" data-guest-list></div>
        <nav class="ownership-actions" aria-label="صفحه‌بندی مراجعه‌ها"><button type="button" class="ownership-button" data-guest-prev disabled>قبلی</button><span data-guest-page-label></span><button type="button" class="ownership-button" data-guest-next disabled>بعدی</button></nav>
    </section>
    <section class="ownership-card ownership-editor" data-guest-editor hidden aria-labelledby="guest-editor-title">
        <div class="ownership-card__heading"><span class="ownership-card__index">۲</span><div><h3 id="guest-editor-title" data-guest-editor-title tabindex="-1">ثبت مراجعه مهمان</h3><p>مشخصات و زمان را وارد کنید؛ سپس درخواست را بررسی و ثبت کنید.</p></div></div>
        <p class="ownership-location-hint" data-guest-editor-unit></p>
        <div class="ownership-flow" aria-label="مراحل ثبت مراجعه">@foreach (['مشخصات مهمان', 'زمان مراجعه', 'بررسی و ثبت'] as $title)<span data-guest-step-label="{{ $loop->index }}"><b>{{ $loop->iteration }}</b>{{ $title }}</span>@endforeach</div>
        <form data-guest-form novalidate>
            <div class="ownership-form-grid" data-guest-step="0">
                <label class="ownership-field"><span>نام مهمان *</span><input name="first_name" required maxlength="255" autocomplete="given-name"></label>
                <label class="ownership-field"><span>نام خانوادگی *</span><input name="last_name" required maxlength="255" autocomplete="family-name"></label>
                <label class="ownership-field"><span>شماره موبایل (اختیاری)</span><input name="mobile" type="tel" maxlength="20" dir="ltr" autocomplete="tel"></label>
                <details class="ownership-more ownership-field--wide"><summary>اطلاعات تکمیلی <small>اختیاری</small></summary><div class="ownership-form-grid"><label class="ownership-field"><span>کد ملی</span><input name="national_code" inputmode="numeric" maxlength="20" dir="ltr"></label><label class="ownership-field"><span>پلاک خودرو</span><input name="vehicle_plate" maxlength="255"></label></div></details>
            </div>
            <div class="ownership-form-grid" data-guest-step="1" hidden>
                <label class="ownership-field"><span>زمان مورد انتظار ورود *</span><input type="datetime-local" name="expected_entry_at" required></label>
                <label class="ownership-field"><span>زمان مورد انتظار خروج (اختیاری)</span><input type="datetime-local" name="expected_exit_at"></label>
                <label class="ownership-field ownership-field--wide"><span>توضیحات (اختیاری)</span><textarea name="description" maxlength="5000" rows="3" placeholder="توضیحی برای هماهنگی مراجعه"></textarea></label>
            </div>
            <div data-guest-step="2" hidden><div class="ownership-record-list" data-guest-review></div><p class="ownership-form-hint">ثبت مراجعه به معنی ورود مهمان نیست؛ اعتبار مجوز هنگام ثبت ورود توسط سامانه بررسی می‌شود.</p></div>
            <div class="ownership-editor__actions"><button class="ownership-button ownership-button--soft" type="button" data-guest-cancel>بازگشت به مراجعه‌ها</button><button class="ownership-button" type="button" data-guest-back hidden>مرحله قبل</button><button class="ownership-button ownership-button--primary" type="submit" data-guest-continue>ادامه</button></div>
        </form>
    </section>
    <section class="ownership-card" data-guest-detail hidden aria-labelledby="guest-detail-title">
        <div class="ownership-card__heading ownership-card__heading--split"><div><h3 id="guest-detail-title" data-guest-detail-title tabindex="-1">مجوز و سابقه مراجعه</h3><p data-guest-detail-status></p></div><button class="ownership-button" type="button" data-guest-close-detail>بازگشت به مراجعه‌ها</button></div>
        <div class="ownership-record-list" data-guest-detail-body></div>
        <h4 class="ownership-subheading">سابقه ورود و خروج</h4><div class="ownership-record-list" data-guest-logs></div>
        <nav class="ownership-actions" aria-label="صفحه‌بندی سوابق"><button class="ownership-button" type="button" data-guest-logs-prev disabled>قبلی</button><span data-guest-logs-page></span><button class="ownership-button" type="button" data-guest-logs-next disabled>بعدی</button></nav>
        <div class="ownership-actions" data-guest-detail-actions></div>
        <form data-guest-access-form hidden><h4 class="ownership-subheading" data-guest-access-title></h4><p class="ownership-form-hint" data-guest-access-hint></p><label class="ownership-field"><span>یادداشت (اختیاری)</span><textarea name="notes" maxlength="5000" rows="2"></textarea></label><div class="ownership-editor__actions"><button type="button" class="ownership-button" data-guest-access-cancel>انصراف</button><button type="submit" class="ownership-button ownership-button--primary" data-guest-access-confirm>ثبت</button></div></form>
    </section>
    <noscript><p class="ownership-alert ownership-alert--error">برای مدیریت مراجعه‌ها، جاوااسکریپت مرورگر را فعال کنید.</p></noscript>
</div>
@endsection
@push('scripts')
<script>window.BuildinoGuestConfig = {{ \Illuminate\Support\Js::from($guestConfig) }};</script>
<script src="{{ asset('js/buildino-guest-visits.js') }}?v={{ filemtime(public_path('js/buildino-guest-visits.js')) }}" defer></script>
@endpush
