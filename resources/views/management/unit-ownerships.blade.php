@extends('management.layouts.app')

@section('title', 'مدیریت مالکیت واحد | Buildino')
@section('page-title', 'مدیریت مالکیت واحد')
@section('page-subtitle', 'مالک‌های هر واحد را ثبت کنید و سوابق تغییر مالکیت را ببینید.')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/buildino-unit-ownerships.css') }}?v={{ filemtime(public_path('css/buildino-unit-ownerships.css')) }}">
@endpush

@section('content')
<div class="ownership-page" data-unit-ownership-page>
    <header class="ownership-hero">
        <div class="ownership-hero__icon">@include('management.partials.icon', ['name' => 'building', 'size' => 27])</div>
        <div>
            <span class="eyebrow">مدیریت ساکنین و مالکیت</span>
            <h2>مالکیت کدام واحد را مدیریت می‌کنید؟</h2>
            <p>ابتدا واحد را پیدا کنید؛ مالک‌های فعلی و سابقه همان واحد در ادامه نمایش داده می‌شوند.</p>
        </div>
    </header>

    <div class="ownership-flow" aria-label="مراحل مدیریت مالکیت">
        <span class="is-current" data-ownership-flow="unit"><b>۱</b> انتخاب واحد</span>
        <span data-ownership-flow="records"><b>۲</b> مالک‌ها و سوابق</span>
        <span data-ownership-flow="change"><b>۳</b> ثبت تغییر</span>
    </div>

    <div class="ownership-alert ownership-alert--error" role="alert" data-ownership-error hidden></div>
    <div class="ownership-alert ownership-alert--success" role="status" aria-live="polite" data-ownership-success hidden></div>

    <section class="ownership-card" aria-labelledby="ownership-unit-heading" data-ownership-location>
        <div class="ownership-card__heading">
            <span class="ownership-card__index">۱</span>
            <div><h3 id="ownership-unit-heading" tabindex="-1">واحد را انتخاب کنید</h3><p>فهرست‌ها فقط ساختمان‌ها و واحدهای قابل دسترسی شما را نشان می‌دهند.</p></div>
        </div>

        @if ($ownershipConfig['buildings']->isEmpty())
            <p class="ownership-empty">ساختمانی با دسترسی مشاهده مالکیت برای حساب شما پیدا نشد.</p>
        @else
            <div class="ownership-location-grid">
                <label class="ownership-field"><span>ساختمان</span><select data-ownership-building aria-label="ساختمان"><option value="">ساختمان را انتخاب کنید</option>@foreach ($ownershipConfig['buildings'] as $building)<option value="{{ $building['id'] }}">{{ $building['title'] }}@if ($building['complexTitle']) — {{ $building['complexTitle'] }}@endif</option>@endforeach</select></label>
                <label class="ownership-field"><span>بلوک</span><select data-ownership-block aria-label="بلوک" disabled><option value="">ابتدا ساختمان را انتخاب کنید</option></select></label>
                <label class="ownership-field"><span>طبقه</span><select data-ownership-floor aria-label="طبقه" disabled><option value="">ابتدا بلوک را انتخاب کنید</option></select></label>
                <label class="ownership-field"><span>واحد</span><select data-ownership-unit aria-label="واحد" disabled><option value="">ابتدا طبقه را انتخاب کنید</option></select></label>
            </div>
            <p class="ownership-location-hint" data-ownership-location-hint aria-live="polite">برای شروع، ساختمان را انتخاب کنید.</p>
            <div class="ownership-editor__actions"><button type="button" class="ownership-button ownership-button--primary" data-ownership-continue hidden>نمایش مالکیت‌های واحد</button></div>
        @endif
    </section>

    <section class="ownership-card" aria-labelledby="ownership-records-heading" data-ownership-records hidden>
        <div class="ownership-card__heading ownership-card__heading--split">
            <span class="ownership-card__index">۲</span>
            <div><h3 id="ownership-records-heading" tabindex="-1">مالکیت‌های این واحد</h3><p data-ownership-unit-label></p></div>
            <button type="button" class="ownership-button ownership-button--soft" data-ownership-back-unit>تغییر واحد</button>
            <button type="button" class="ownership-button ownership-button--soft" data-ownership-reload>به‌روزرسانی</button>
        </div>
        <div class="ownership-overview" data-ownership-overview aria-live="polite"></div>
        <div class="ownership-record-list" data-ownership-active></div>
        <details class="ownership-history" data-ownership-history-wrap hidden>
            <summary>نمایش سوابق پایان‌یافته <span data-ownership-history-count></span></summary>
            <div class="ownership-record-list" data-ownership-history></div>
        </details>
        <div class="ownership-actions" data-ownership-actions hidden>
            <button type="button" class="ownership-button ownership-button--primary" data-ownership-add>+ ثبت مالک جدید</button>
            <span>برای ثبت مالک، حساب کاربری او باید از قبل ساخته شده باشد.</span>
            <a href="{{ $ownershipConfig['usersUrl'] }}" data-ownership-create-user hidden>مدیریت کاربران</a>
        </div>
    </section>

    <section class="ownership-card ownership-editor" aria-labelledby="ownership-editor-heading" data-ownership-editor hidden>
        <div class="ownership-card__heading"><span class="ownership-card__index">۳</span><div><h3 id="ownership-editor-heading" tabindex="-1" data-ownership-editor-title>ثبت مالک جدید</h3><p data-ownership-editor-unit-label></p><p>درصد مالکیت همه مالک‌های فعال نباید از ۱۰۰ بیشتر شود.</p></div></div>
        <form data-ownership-form novalidate>
            <div class="ownership-form-grid">
                <div class="ownership-field ownership-field--wide" data-ownership-user-picker>
                    <label for="ownership-user-search">مالک</label>
                    <input id="ownership-user-search" type="search" data-ownership-user-search placeholder="نام کاربر موجود یا شماره موبایل کامل" autocomplete="off">
                    <select name="user_id" data-ownership-user aria-label="انتخاب مالک" required><option value="">ابتدا مالک را جست‌وجو کنید</option></select>
                    <small data-ownership-user-hint>برای حسابی که هنوز به ساختمان وصل نیست، شماره موبایل ۱۱ رقمی یا ایمیل کامل او را وارد کنید.</small>
                </div>
                <div class="ownership-field ownership-field--wide" data-ownership-edit-owner hidden><span>مالک</span><strong data-ownership-edit-name></strong></div>
                <label class="ownership-field"><span>درصد مالکیت</span><input type="number" name="ownership_percentage" min="0" max="100" step="0.01" inputmode="decimal" dir="ltr" placeholder="مثلاً ۵۰"></label>
                <label class="ownership-field"><span>تاریخ شروع</span><input type="date" name="starts_at" required></label>
                <label class="ownership-check ownership-field--wide"><input type="checkbox" name="is_primary" value="1"><span><strong>مالک اصلی این واحد است</strong><small>در هر زمان فقط یک نفر می‌تواند مالک اصلی باشد. انتخاب این گزینه، مالک اصلی قبلی را تغییر می‌دهد.</small></span></label>
                <details class="ownership-more ownership-field--wide" data-ownership-more><summary>اطلاعات تکمیلی <small>اختیاری</small></summary><div class="ownership-form-grid"><label class="ownership-field" data-ownership-create-end><span>تاریخ پایان مشخص است؟</span><input type="date" name="ends_at"></label><label class="ownership-field ownership-field--wide"><span>یادداشت داخلی</span><textarea name="notes" rows="3" maxlength="5000" placeholder="توضیحی برای پیگیری این تغییر"></textarea></label></div></details>
            </div>
            <p class="ownership-form-hint" data-ownership-share-hint></p>
            <div class="ownership-editor__actions"><button type="button" class="ownership-button ownership-button--soft" data-ownership-cancel>بازگشت به مالک‌ها</button><button type="submit" class="ownership-button ownership-button--primary" data-ownership-save>ثبت مالکیت</button></div>
        </form>
    </section>

    <div class="ownership-dialog-backdrop" data-ownership-end-backdrop hidden></div>
    <dialog class="ownership-dialog" data-ownership-end-dialog aria-modal="true" aria-labelledby="ownership-end-title">
        <form data-ownership-end-form novalidate>
            <h3 id="ownership-end-title" tabindex="-1">پایان مالکیت</h3>
            <p>این مالکیت در سابقه واحد می‌ماند، اما دیگر فعال نخواهد بود.</p>
            <strong data-ownership-end-name></strong>
            <label class="ownership-field"><span>تاریخ پایان</span><input type="date" name="ends_at" required></label>
            <div class="ownership-dialog__actions"><button type="button" class="ownership-button ownership-button--soft" data-ownership-end-cancel>انصراف</button><button type="submit" class="ownership-button ownership-button--danger" data-ownership-end-confirm>تأیید پایان مالکیت</button></div>
        </form>
    </dialog>
    <noscript><p class="ownership-alert ownership-alert--error">برای مدیریت مالکیت، جاوااسکریپت مرورگر را فعال کنید.</p></noscript>
</div>
@endsection

@push('scripts')
    <script>window.BuildinoOwnershipConfig = {{ \Illuminate\Support\Js::from($ownershipConfig) }};</script>
    <script src="{{ asset('js/buildino-unit-ownerships.js') }}?v={{ filemtime(public_path('js/buildino-unit-ownerships.js')) }}" defer></script>
@endpush
