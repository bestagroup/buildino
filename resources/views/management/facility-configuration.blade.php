@extends('management.layouts.app')
@section('title', 'تعریف و پیکربندی امکانات | Buildino')
@section('page-title', 'تعریف و پیکربندی امکانات ساختمان')
@section('page-subtitle', 'امکانات را تعریف کنید، زمان‌بندی و قوانین را تنظیم کنید و سپس فعال کنید.')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/buildino-unit-ownerships.css') }}?v={{ filemtime(public_path('css/buildino-unit-ownerships.css')) }}">
<link rel="stylesheet" href="{{ asset('css/buildino-facilities.css') }}?v={{ filemtime(public_path('css/buildino-facilities.css')) }}">
@endpush
@section('content')
<div class="ownership-page" data-facility-page data-core-process-ui>
    <header class="ownership-hero"><div class="ownership-hero__icon">@include('management.partials.icon', ['name' => 'building', 'size' => 27])</div><div><span class="eyebrow">امکانات ساختمان</span><h2>تعریف و پیکربندی امکانات</h2><p>اطلاعات، برنامه هفتگی و قوانین هر امکان را تنظیم کنید.</p></div></header>
    <div class="ownership-alert ownership-alert--error" data-facility-error role="alert" hidden></div>
    <div class="ownership-alert ownership-alert--success" data-facility-success role="status" aria-live="polite" hidden></div>
    <section class="ownership-card" data-facility-location aria-labelledby="facility-location-title">
        <div class="ownership-card__heading"><div><h3 id="facility-location-title" tabindex="-1">امکانات کدام ساختمان؟</h3><p>ساختمان را انتخاب کنید تا امکانات فعال و غیرفعال آن را ببینید.</p></div></div>
        <label class="ownership-field"><span>ساختمان</span><select data-facility-building><option value="">انتخاب ساختمان</option>@foreach ($facilityConfig['buildings'] as $building)<option value="{{ $building['id'] }}">{{ $building['title'] }}</option>@endforeach</select></label>
        @if ($facilityConfig['buildings']->isEmpty())<p class="ownership-empty">ساختمان قابل دسترسی برای مشاهده امکانات وجود ندارد.</p>@endif
        <div class="ownership-actions"><button type="button" class="ownership-button ownership-button--primary" data-facility-location-continue hidden>نمایش امکانات ساختمان</button></div>
    </section>
    <section class="ownership-card" data-facility-directory hidden>
        <div class="ownership-card__heading ownership-card__heading--split"><div><h3 id="facility-directory-title" tabindex="-1">امکانات ساختمان</h3><p data-facility-building-label></p></div><div class="facility-toolbar"><button type="button" class="ownership-button" data-facility-change-building>تغییر ساختمان</button><button type="button" class="ownership-button" data-facility-reload>به‌روزرسانی</button><button type="button" class="ownership-button ownership-button--primary" data-facility-new hidden>+ امکان جدید</button></div></div>
        <label class="ownership-field facility-search"><span>جست‌وجوی عنوان یا کد امکان</span><input type="search" data-facility-search placeholder="مثلاً استخر یا سالن جلسات"></label>
        <p class="ownership-location-hint" data-facility-list-status role="status" aria-live="polite"></p>
        <div class="ownership-record-list facility-block" data-facility-list></div>
        <nav class="ownership-actions facility-pagination" aria-label="صفحه‌بندی امکانات"><button type="button" class="ownership-button" data-facility-prev disabled>قبلی</button><span data-facility-pagination></span><button type="button" class="ownership-button" data-facility-next disabled>بعدی</button></nav>
    </section>
    <section class="ownership-card" data-facility-editor hidden aria-labelledby="facility-editor-title">
        <div class="ownership-card__heading ownership-card__heading--split"><div><h3 id="facility-editor-title" data-facility-editor-title tabindex="-1">پیکربندی امکان</h3><p data-facility-editor-context></p></div><button type="button" class="ownership-button" data-facility-close>بازگشت به امکانات</button></div>
        <nav class="facility-steps" aria-label="مراحل پیکربندی">@foreach (['اطلاعات و هزینه', 'برنامه و سانس‌ها', 'قوانین رزرو', 'بررسی و فعال‌سازی'] as $label)<button type="button" data-facility-step-button="{{ $loop->index }}"><b aria-hidden="true">{{ $loop->iteration }}</b><span>{{ $label }}</span></button>@endforeach</nav>
        <div class="facility-step-heading"><h4 data-facility-panel-title tabindex="-1"></h4><p class="ownership-form-hint" data-facility-save-state role="status" aria-live="polite"></p><button type="button" class="ownership-button" data-facility-refresh hidden>دریافت دوباره اطلاعات ذخیره‌شده</button></div>
        <div data-facility-panel="0">
            <form data-facility-base-form novalidate>
                <fieldset data-facility-base-fields class="border-0 p-0 m-0 w-100"><div class="ownership-form-grid">
                    <label class="ownership-field"><span>عنوان امکان *</span><input name="title" required maxlength="255" placeholder="مثلاً سالن اجتماعات"></label>
                    <label class="ownership-field"><span>کد امکان *</span><input name="code" required maxlength="100" dir="ltr" placeholder="مثلاً HALL-01"><small>کدی یکتا در این ساختمان</small></label>
                    <label class="ownership-field"><span>نوع امکان *</span><select name="type" required><option value="">انتخاب کنید</option><option value="gym">باشگاه ورزشی</option><option value="pool">استخر</option><option value="roof_garden">روف‌گاردن</option><option value="meeting_hall">سالن جلسات / اجتماعات</option><option value="other">سایر</option></select></label>
                    <label class="ownership-field"><span>ظرفیت پایه</span><input name="capacity" type="number" min="1" max="100000" step="1" placeholder="اختیاری"></label>
                    <label class="ownership-field"><span>هزینه پایه (<span data-facility-currency>واحد پول ساختمان</span>) *</span><input name="default_price" type="number" min="0" step="1" value="0" required><small>برای استفاده رایگان، صفر وارد کنید.</small></label>
                    <div class="ownership-field"><span>پرداخت</span><label class="buildino-switch"><input class="buildino-switch__input" name="requires_payment" type="checkbox" role="switch" aria-label="نیاز به پرداخت دارد"><span class="buildino-switch__track" aria-hidden="true"></span><span class="buildino-switch__label">نیاز به پرداخت دارد</span><span class="buildino-switch__state" aria-hidden="true"><span class="buildino-switch__on">بله</span><span class="buildino-switch__off">خیر</span></span></label><small>هزینه هر سانس در مرحله بعد مشخص می‌شود.</small></div>
                    <label class="ownership-field ownership-field--wide"><span>مکان و توضیحات</span><textarea name="description" rows="3" maxlength="5000" placeholder="مثلاً طبقه همکف، کنار لابی؛ توضیحی برای استفاده‌کنندگان"></textarea></label>
                </div></fieldset>
                <div class="ownership-editor__actions"><button type="button" class="ownership-button" data-facility-view-next="1" hidden>مشاهده برنامه و سانس‌ها</button><button type="submit" class="ownership-button ownership-button--primary" data-facility-base-save>ذخیره و ادامه</button></div>
            </form>
        </div>
        <div data-facility-panel="1" hidden>
            <p class="ownership-form-hint">ابتدا روزها و بازه فعالیت را ثبت کنید؛ سپس برای هر برنامه، سانس قابل رزرو بسازید.</p>
            <h4 class="ownership-subheading">برنامه‌ها و سانس‌های ثبت‌شده</h4>
            <div class="ownership-record-list" data-facility-schedules></div>
            <div class="facility-planning-forms">
            <form class="facility-form-section" data-facility-schedule-form novalidate>
                <h4 data-facility-schedule-title>افزودن برنامه هفتگی</h4>
                <p class="ownership-form-hint">روزهای دارای ساعت فعالیت یکسان را با هم انتخاب و ذخیره کنید.</p>
                <fieldset data-facility-schedule-fields class="border-0 p-0 m-0 w-100">
                    <fieldset class="facility-days"><legend>روزهای فعالیت *</legend>@foreach ([6 => 'شنبه', 0 => 'یکشنبه', 1 => 'دوشنبه', 2 => 'سه‌شنبه', 3 => 'چهارشنبه', 4 => 'پنجشنبه', 5 => 'جمعه'] as $day => $label)<label><input type="checkbox" name="days" value="{{ $day }}">{{ $label }}</label>@endforeach</fieldset>
                    <div class="ownership-form-grid facility-block"><label class="ownership-field"><span>شروع فعالیت *</span><input type="time" data-native-date name="start_time" required dir="ltr"></label><label class="ownership-field"><span>پایان فعالیت *</span><input type="time" data-native-date name="end_time" required dir="ltr"></label><label class="buildino-switch ownership-field--wide"><input class="buildino-switch__input" name="is_active" type="checkbox" role="switch" aria-label="برنامه فعال است" checked><span class="buildino-switch__track" aria-hidden="true"></span><span class="buildino-switch__label">وضعیت برنامه</span><span class="buildino-switch__state" aria-hidden="true"><span class="buildino-switch__on">فعال</span><span class="buildino-switch__off">غیرفعال</span></span></label></div>
                </fieldset>
                <div class="ownership-editor__actions"><button type="button" class="ownership-button" data-facility-schedule-reset>برنامه جدید</button><button type="submit" class="ownership-button ownership-button--primary" data-facility-schedule-save>ذخیره برنامه</button></div>
                <p class="ownership-form-hint">ساعت‌ها در قالب ۲۴ ساعته‌اند؛ پایان فعالیت باید بعد از شروع همان روز باشد.</p>
            </form>
            <form class="facility-form-section" data-facility-slot-form novalidate>
                <h4 data-facility-slot-title>افزودن سانس قابل رزرو</h4>
                <p class="ownership-form-hint" data-facility-slot-hint>پس از ذخیره برنامه هفتگی، ساعت و ظرفیت هر سانس را تعیین کنید.</p>
                <fieldset data-facility-slot-fields class="border-0 p-0 m-0 w-100"><div class="ownership-form-grid">
                    <label class="ownership-field ownership-field--wide"><span>برنامه هفتگی *</span><select name="schedule_id" required><option value="">ابتدا برنامه هفتگی را ثبت کنید</option></select></label>
                    <label class="ownership-field"><span>شروع سانس *</span><input type="time" data-native-date name="start_time" required dir="ltr"></label><label class="ownership-field"><span>پایان سانس *</span><input type="time" data-native-date name="end_time" required dir="ltr"></label>
                    <label class="ownership-field"><span>ظرفیت این سانس *</span><input type="number" name="capacity" min="1" max="100000" step="1" value="1" required></label><label class="ownership-field"><span>هزینه این سانس (<span data-facility-currency>واحد پول ساختمان</span>) *</span><input type="number" name="price" min="0" step="1" value="0" required></label>
                    <label class="buildino-switch ownership-field--wide"><input class="buildino-switch__input" name="is_active" type="checkbox" role="switch" aria-label="سانس فعال است" checked><span class="buildino-switch__track" aria-hidden="true"></span><span class="buildino-switch__label">وضعیت سانس</span><span class="buildino-switch__state" aria-hidden="true"><span class="buildino-switch__on">فعال</span><span class="buildino-switch__off">غیرفعال</span></span></label>
                </div></fieldset>
                <p class="ownership-form-hint">سانس باید داخل بازه برنامه باشد و با سانس دیگری هم‌پوشانی نداشته باشد. ظرفیت و قیمت رزرو از همین سانس گرفته می‌شود.</p>
                <div class="ownership-editor__actions"><button type="button" class="ownership-button" data-facility-slot-reset>سانس جدید</button><button type="submit" class="ownership-button ownership-button--primary" data-facility-slot-save>ذخیره سانس</button></div>
            </form>
            </div>
            <div class="ownership-actions facility-navigation"><button type="button" class="ownership-button" data-facility-go="0">مرحله قبل</button><button type="button" class="ownership-button ownership-button--primary" data-facility-go="2">ادامه به قوانین رزرو</button></div>
        </div>
        <div data-facility-panel="2" hidden>
            <p class="ownership-form-hint facility-rule-hint">مدت سانس‌های تعریف‌شده باید با حداقل و حداکثر مدت رزرو سازگار باشد. فیلدهای اختیاری را برای نداشتن محدودیت خالی بگذارید.</p>
            <form data-facility-rule-form novalidate>
                <fieldset data-facility-rule-fields class="border-0 p-0 m-0 w-100"><div class="ownership-form-grid">
                    @foreach (['min_duration_minutes' => ['حداقل مدت رزرو (دقیقه)', 1, 1440, ''], 'max_duration_minutes' => ['حداکثر مدت رزرو (دقیقه)', 1, 1440, 60], 'min_advance_minutes' => ['فاصله رزرو تا شروع (دقیقه)', 0, '', 0], 'max_advance_days' => ['امکان رزرو تا چند روز آینده', 1, 3650, ''], 'max_reservation_per_unit' => ['حداکثر رزرو فعال هر واحد', 1, '', 1], 'cancel_before_minutes' => ['مهلت لغو پیش از شروع (دقیقه)', 0, '', 0]] as $name => $field)
                    <label class="ownership-field"><span>{{ $field[0] }}</span><input type="number" name="{{ $name }}" min="{{ $field[1] }}" @if ($field[2] !== '') max="{{ $field[2] }}" @endif value="{{ $field[3] }}" step="1" @if ($field[3] !== '') required @endif placeholder="{{ $field[3] === '' ? 'بدون محدودیت' : 'مقدار را وارد کنید' }}"></label>
                    @endforeach
                    <div class="facility-settings ownership-field--wide">
                        <h4>تنظیمات رزرو</h4>
                        <label class="buildino-switch"><input class="buildino-switch__input" type="checkbox" name="requires_approval" role="switch" aria-label="رزرو نیاز به تأیید مدیر دارد"><span class="buildino-switch__track" aria-hidden="true"></span><span class="buildino-switch__label">نیاز به تأیید مدیر</span><span class="buildino-switch__state" aria-hidden="true"><span class="buildino-switch__on">بله</span><span class="buildino-switch__off">خیر</span></span></label>
                        <label class="buildino-switch"><input class="buildino-switch__input" type="checkbox" name="auto_confirm" role="switch" aria-label="تأیید خودکار رزرو"><span class="buildino-switch__track" aria-hidden="true"></span><span class="buildino-switch__label">تأیید خودکار رزرو</span><span class="buildino-switch__state" aria-hidden="true"><span class="buildino-switch__on">فعال</span><span class="buildino-switch__off">غیرفعال</span></span></label>
                        <label class="buildino-switch"><input class="buildino-switch__input" type="checkbox" name="allow_guest" role="switch" aria-label="استفاده مهمان مجاز است"><span class="buildino-switch__track" aria-hidden="true"></span><span class="buildino-switch__label">استفاده مهمان</span><span class="buildino-switch__state" aria-hidden="true"><span class="buildino-switch__on">مجاز</span><span class="buildino-switch__off">غیرمجاز</span></span></label>
                        <p class="ownership-form-hint" data-facility-approval-hint>وقتی تأیید مدیر لازم است، تأیید خودکار انجام نمی‌شود.</p>
                    </div>
                    <details class="ownership-more ownership-field--wide"><summary>محدودیت‌های بیشتر و شرایط لغو</summary><div class="ownership-form-grid">
                        @foreach (['max_reservations_per_day' => 'حداکثر رزرو روزانه', 'max_reservations_per_week' => 'حداکثر رزرو هفتگی', 'max_reservations_per_month' => 'حداکثر رزرو ماهانه'] as $name => $label)<label class="ownership-field"><span>{{ $label }}</span><input type="number" name="{{ $name }}" min="1" step="1" placeholder="بدون محدودیت"></label>@endforeach
                        <label class="ownership-field"><span>هزینه لغو (<span data-facility-currency>واحد پول ساختمان</span>)</span><input type="number" name="cancellation_fee" min="0" step="1" value="0" required></label><label class="ownership-field"><span>درصد بازپرداخت</span><input type="number" name="refund_percentage" min="0" max="100" step="1" value="100" required></label>
                    </div></details>
                </div></fieldset>
                <div class="ownership-editor__actions"><button type="button" class="ownership-button" data-facility-go="1">مرحله قبل</button><button type="button" class="ownership-button" data-facility-view-next="3" hidden>مشاهده بررسی نهایی</button><button type="submit" class="ownership-button ownership-button--primary" data-facility-rule-save>ذخیره قوانین و ادامه</button></div>
            </form>
        </div>
        <div data-facility-panel="3" hidden>
            <h4 class="ownership-subheading">بررسی پیش از فعال‌سازی</h4><p class="ownership-form-hint">این فهرست بر اساس آخرین اطلاعات ذخیره‌شده تهیه می‌شود.</p>
            <div class="ownership-record-list" data-facility-checklist aria-live="polite"></div>
            <div class="ownership-editor__actions"><button type="button" class="ownership-button" data-facility-go="2">مرحله قبل</button><button type="button" class="ownership-button" data-facility-check>بررسی مجدد اطلاعات</button><button type="button" class="ownership-button ownership-button--primary" data-facility-activate disabled>فعال‌سازی امکان</button></div>
            <p class="ownership-form-hint" data-facility-activation-state role="status"></p>
        </div>
    </section>
    <noscript><p class="ownership-alert ownership-alert--error">برای پیکربندی امکانات، جاوااسکریپت مرورگر را فعال کنید.</p></noscript>
</div>
@endsection
@push('scripts')
<script>window.BuildinoFacilityConfig = {{ \Illuminate\Support\Js::from($facilityConfig) }};</script>
<script src="{{ asset('js/buildino-facilities.js') }}?v={{ filemtime(public_path('js/buildino-facilities.js')) }}" defer></script>
@endpush
