@extends('management.layouts.app')

@section('title', 'مدیریت سکونت واحد | Buildino')
@section('page-title', 'مدیریت سکونت واحد')
@section('page-subtitle', 'ساکنان هر واحد و بازه سکونت آن‌ها را مدیریت کنید.')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/buildino-unit-ownerships.css') }}?v={{ filemtime(public_path('css/buildino-unit-ownerships.css')) }}">
@endpush

@section('content')
<div class="ownership-page" data-unit-occupancy-page>
    <header class="ownership-hero">
        <div class="ownership-hero__icon">@include('management.partials.icon', ['name' => 'building', 'size' => 27])</div>
        <div><span class="eyebrow">مدیریت ساکنین و مالکیت</span><h2>سکونت کدام واحد را مدیریت می‌کنید؟</h2><p>واحد را انتخاب کنید تا ساکنان فعلی و سوابق آن را ببینید.</p></div>
    </header>
    <div class="ownership-flow" aria-label="مراحل مدیریت سکونت">
        <span class="is-current" data-occupancy-flow="unit"><b>۱</b> انتخاب واحد</span>
        <span data-occupancy-flow="records"><b>۲</b> ساکنان واحد</span>
        <span data-occupancy-flow="change"><b>۳</b> ثبت رابطه و تاریخ</span>
    </div>
    <div class="ownership-alert ownership-alert--error" role="alert" data-occupancy-error hidden></div>
    <div class="ownership-alert ownership-alert--success" role="status" aria-live="polite" data-occupancy-success hidden></div>

    <section class="ownership-card" aria-labelledby="occupancy-unit-heading" data-occupancy-location>
        <div class="ownership-card__heading"><span class="ownership-card__index">۱</span><div><h3 id="occupancy-unit-heading" tabindex="-1">واحد را انتخاب کنید</h3><p>فقط ساختمان‌ها و واحدهای قابل دسترسی شما نمایش داده می‌شوند.</p></div></div>
        @if ($occupancyConfig['buildings']->isEmpty())
            <p class="ownership-empty">ساختمانی با دسترسی مشاهده سکونت برای حساب شما پیدا نشد.</p>
        @else
            <div class="ownership-location-grid">
                <label class="ownership-field"><span>ساختمان</span><select data-occupancy-building><option value="">ساختمان را انتخاب کنید</option>@foreach ($occupancyConfig['buildings'] as $building)<option value="{{ $building['id'] }}">{{ $building['title'] }}@if ($building['complexTitle']) — {{ $building['complexTitle'] }}@endif</option>@endforeach</select></label>
                <label class="ownership-field"><span>بلوک</span><select data-occupancy-block disabled><option value="">ابتدا ساختمان را انتخاب کنید</option></select></label>
                <label class="ownership-field"><span>طبقه</span><select data-occupancy-floor disabled><option value="">ابتدا بلوک را انتخاب کنید</option></select></label>
                <label class="ownership-field"><span>واحد</span><select data-occupancy-unit disabled><option value="">ابتدا طبقه را انتخاب کنید</option></select></label>
            </div>
            <p class="ownership-location-hint" data-occupancy-location-hint aria-live="polite">برای شروع، ساختمان را انتخاب کنید.</p>
            <div class="ownership-editor__actions"><button type="button" class="ownership-button ownership-button--primary" data-occupancy-continue hidden>نمایش ساکنان واحد</button></div>
        @endif
    </section>

    <section class="ownership-card" data-occupancy-records hidden aria-labelledby="occupancy-records-heading">
        <div class="ownership-card__heading ownership-card__heading--split"><span class="ownership-card__index">۲</span><div><h3 id="occupancy-records-heading" tabindex="-1">ساکنان این واحد</h3><p data-occupancy-unit-label></p></div><button type="button" class="ownership-button ownership-button--soft" data-occupancy-back-unit>تغییر واحد</button><button type="button" class="ownership-button ownership-button--soft" data-occupancy-reload>به‌روزرسانی</button></div>
        <div class="ownership-overview" data-occupancy-overview aria-live="polite"></div>
        <div class="ownership-record-list" data-occupancy-active></div>
        <div data-occupancy-pending-wrap hidden><h4 class="ownership-subheading">در انتظار پذیرش دعوت</h4><div class="ownership-record-list" data-occupancy-pending></div></div>
        <details class="ownership-history" data-occupancy-history-wrap hidden><summary>سوابق پایان‌یافته <span data-occupancy-history-count></span></summary><div class="ownership-record-list" data-occupancy-history></div></details>
        <div class="ownership-actions" data-occupancy-actions hidden><button type="button" class="ownership-button ownership-button--primary" data-occupancy-add>+ ثبت ساکن</button><span>ساکن باید حساب کاربری فعال داشته باشد.</span><a href="{{ $occupancyConfig['usersUrl'] }}" data-occupancy-create-user hidden>ایجاد حساب در مدیریت کاربران</a></div>
    </section>

    <section class="ownership-card ownership-editor" data-occupancy-editor hidden aria-labelledby="occupancy-editor-heading">
        <div class="ownership-card__heading"><span class="ownership-card__index">۳</span><div><h3 id="occupancy-editor-heading" data-occupancy-editor-title tabindex="-1">ثبت ساکن</h3><p data-occupancy-editor-unit-label></p><p>رابطه سکونت مستقل از مالکیت و نقش دسترسی است.</p></div></div>
        <form data-occupancy-form novalidate>
            <div class="ownership-form-grid">
                <div class="ownership-field ownership-field--wide" data-occupancy-user-picker><label for="occupancy-user-search">شخص</label><input id="occupancy-user-search" type="search" data-occupancy-user-search placeholder="نام شخص یا شماره موبایل کامل" autocomplete="off"><select name="user_id" data-occupancy-user required aria-label="انتخاب ساکن"><option value="">ابتدا شخص را جست‌وجو کنید</option></select><small data-occupancy-user-hint>برای شخصی خارج از ساختمان، شماره موبایل ۱۱ رقمی یا ایمیل کامل را جست‌وجو کنید.</small><small data-occupancy-account-status role="status" aria-live="polite"></small></div>
                <div class="ownership-field ownership-field--wide" data-occupancy-edit-person hidden><span>شخص</span><strong data-occupancy-edit-name></strong></div>
                <label class="ownership-field"><span>نوع رابطه با واحد</span><select name="occupancy_type" required><option value="">انتخاب کنید</option><option value="tenant">مستأجر</option><option value="resident">ساکن</option><option value="family_member">عضو خانواده</option><option value="owner" data-occupancy-owner-option hidden>مالک ساکن</option></select></label>
                <label class="ownership-field"><span>تاریخ شروع سکونت</span><input type="date" name="starts_at" required></label>
                <label class="ownership-check ownership-field--wide"><input type="checkbox" name="is_primary" value="1"><span><strong>ساکن اصلی این واحد است</strong><small>اگر ساکن اصلی دیگری وجود داشته باشد، سامانه این عنوان را به شخص جدید منتقل می‌کند.</small></span></label>
                <label class="ownership-check ownership-field--wide" data-occupancy-invite-wrap hidden><input type="checkbox" name="send_invitation" value="1"><span><strong>ابتدا دعوت ورود بفرست</strong><small>سکونت فقط پس از پذیرش دعوت توسط این شخص فعال می‌شود. اگر دعوت لازم نیست، سکونت مستقیم ثبت می‌شود.</small></span></label>
                <details class="ownership-more ownership-field--wide" data-occupancy-more><summary>اطلاعات تکمیلی <small>اختیاری</small></summary><div class="ownership-form-grid"><label class="ownership-field" data-occupancy-create-end><span>تاریخ پایان سکونت</span><input type="date" name="ends_at"></label><label class="ownership-field ownership-field--wide"><span>یادداشت داخلی</span><textarea name="notes" rows="3" maxlength="5000" placeholder="توضیحی برای پیگیری"></textarea></label></div></details>
            </div>
            <p class="ownership-form-hint">در حالت ثبت مستقیم، سکونت بلافاصله فعال است. در حالت دعوت، اطلاعات رابطه و تاریخ تا زمان پذیرش نگهداری می‌شود.</p>
            <div class="ownership-editor__actions"><button type="button" class="ownership-button ownership-button--soft" data-occupancy-cancel>بازگشت به ساکنان</button><button type="submit" class="ownership-button ownership-button--primary" data-occupancy-save>ثبت سکونت</button></div>
        </form>
    </section>

    <div class="ownership-dialog-backdrop" data-occupancy-end-backdrop hidden></div>
    <dialog class="ownership-dialog" data-occupancy-end-dialog aria-labelledby="occupancy-end-title"><form data-occupancy-end-form novalidate><h3 id="occupancy-end-title" tabindex="-1">پایان سکونت</h3><p>این رابطه در سوابق واحد باقی می‌ماند.</p><strong data-occupancy-end-name></strong><label class="ownership-field"><span>تاریخ پایان</span><input type="date" name="ends_at" required></label><div class="ownership-dialog__actions"><button type="button" class="ownership-button ownership-button--soft" data-occupancy-end-cancel>انصراف</button><button type="submit" class="ownership-button ownership-button--danger" data-occupancy-end-confirm>تأیید پایان سکونت</button></div></form></dialog>
    <noscript><p class="ownership-alert ownership-alert--error">برای مدیریت سکونت، جاوااسکریپت مرورگر را فعال کنید.</p></noscript>
</div>
@endsection

@push('scripts')
    <script>window.BuildinoOccupancyConfig = {{ \Illuminate\Support\Js::from($occupancyConfig) }};</script>
    <script src="{{ asset('js/buildino-unit-occupancies.js') }}?v={{ filemtime(public_path('js/buildino-unit-occupancies.js')) }}" defer></script>
@endpush
