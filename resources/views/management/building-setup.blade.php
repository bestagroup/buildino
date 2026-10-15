@extends('management.layouts.app')

@section('title', 'راه‌اندازی ساختمان')
@section('page-title', 'راه‌اندازی ساختمان')
@section('page-subtitle', 'اطلاعات ساختمان و واحدها را در یک مسیر ساده تکمیل کنید.')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/buildino-building-setup.css') }}">
@endpush

@section('content')
<div class="building-setup" data-building-setup>
    <header class="setup-intro">
        <div class="setup-intro__icon">@include('management.partials.icon', ['name' => 'building', 'size' => 28])</div>
        <div><span class="eyebrow">از ساختمان تا اولین واحد</span><h2>ساختمان‌تان را آماده کنید</h2><p>تعداد طبقات و واحدها را مشخص کنید؛ فرم بقیه مسیر را برایتان آماده می‌کند.</p></div>
    </header>

    <div class="setup-resume" data-setup-resume hidden><span>پیش‌نویس راه‌اندازی قبلی شما بازیابی شد.</span><button type="button" data-setup-reset>شروع راه‌اندازی تازه</button></div>
    <nav class="setup-steps" aria-label="مراحل راه‌اندازی">
        @foreach (['ساختمان و نشانی', 'بلوک‌ها و طبقات', 'واحدها', 'بازبینی و ثبت'] as $title)
            <button type="button" data-setup-step-link="{{ $loop->index }}" @if ($loop->first) aria-current="step" @endif><span>{{ ['۱', '۲', '۳', '۴'][$loop->index] }}</span><strong>{{ $title }}</strong></button>
        @endforeach
    </nav>

    <form class="setup-card" data-setup-form novalidate>
        <div class="setup-error" role="alert" data-setup-error hidden></div>
        <section data-setup-step="0" aria-labelledby="setup-building-title">
            <div class="setup-section-heading"><span>گام اول</span><h3 id="setup-building-title" tabindex="-1">کدام ساختمان را راه‌اندازی می‌کنید؟</h3><p>ساختمان ساخته‌شده هنگام ثبت‌نام را انتخاب کنید یا ساختمان جدیدی اضافه کنید.</p></div>
            <div class="setup-choice-grid" role="group" aria-label="روش شروع">
                <label class="setup-choice"><input type="radio" name="mode" value="existing" data-setup-field="mode"><span><strong>تکمیل ساختمان موجود</strong><small>ادامه اطلاعات ساختمان خودم</small></span></label>
                <label class="setup-choice" @if (! $setupConfig['canCreateBuilding']) hidden @endif><input type="radio" name="mode" value="new" data-setup-field="mode"><span><strong>ساختمان جدید</strong><small>ساخت یک فضای جدید برای مدیریت</small></span></label>
            </div>
            <div data-setup-existing>
                <label class="setup-field"><span>ساختمان شما</span><select name="buildingId" data-setup-field="buildingId" data-required="1" data-placeholder="ساختمان را انتخاب کنید"><option value="">ساختمان را انتخاب کنید</option>@foreach ($setupConfig['buildings'] as $building)<option value="{{ $building['id'] }}">{{ $building['title'] }}@if ($building['complexTitle']) — {{ $building['complexTitle'] }}@endif</option>@endforeach</select></label>
                <p class="setup-note">بلوک‌ها، طبقات و واحدهای ثبت‌شده با علامت «ثبت‌شده» مشخص می‌شوند؛ می‌توانید موارد جدید را به آن‌ها اضافه کنید.</p>
                @if ($setupConfig['buildings']->isEmpty())<p class="setup-note">هنوز ساختمانی برای انتخاب ندارید. از گزینه ساختمان جدید شروع کنید.</p>@endif
            </div>
            <div class="setup-fields" data-setup-new hidden>
                <label class="setup-field setup-wide"><span>نام ساختمان</span><input type="text" name="title" data-setup-field="title" maxlength="255" placeholder="مثلاً ساختمان سرو" autocomplete="organization"></label>
                <label class="setup-field setup-wide"><span>این ساختمان در یک مجتمع قرار دارد؟</span><select name="complexMode" data-setup-field="complexMode" data-required="1">
                    @if ($setupConfig['canCreateComplex'])<option value="standalone">خیر، ساختمان مستقل است</option><option value="new">بله، یک مجتمع جدید تعریف می‌کنم</option>@endif
                    @if ($setupConfig['complexes']->isNotEmpty())<option value="existing">بله، مجتمع قبلاً ثبت شده است</option>@endif
                </select></label>
                <label class="setup-field setup-wide" data-setup-complex-name hidden><span>نام مجتمع</span><input type="text" name="complexTitle" data-setup-field="complexTitle" maxlength="255" placeholder="مثلاً مجتمع بهار"></label>
                <label class="setup-field setup-wide" data-setup-complex-existing hidden><span>مجتمع</span><select name="complexId" data-setup-field="complexId" data-required="1" data-placeholder="مجتمع را انتخاب کنید"><option value="">مجتمع را انتخاب کنید</option>@foreach ($setupConfig['complexes'] as $complex)<option value="{{ $complex['id'] }}">{{ $complex['title'] }}</option>@endforeach</select></label>
                <div class="setup-fields setup-wide" data-setup-location>
                    <label class="setup-field"><span>استان</span><select name="provinceId" data-setup-field="provinceId" data-required="1" data-placeholder="انتخاب استان"><option value="">انتخاب استان</option>@foreach ($setupConfig['locations'] as $province)<option value="{{ $province['id'] }}">{{ $province['name'] }}</option>@endforeach</select></label>
                    <label class="setup-field"><span>شهر</span><select name="cityId" data-setup-field="cityId" data-required="1" data-placeholder="انتخاب شهر"><option value="">ابتدا استان را انتخاب کنید</option></select></label>
                </div>
                <label class="setup-field setup-wide"><span>نشانی <small>اختیاری</small></span><textarea name="address" data-setup-field="address" rows="2" maxlength="2000" placeholder="خیابان، کوچه و پلاک" autocomplete="street-address"></textarea></label>
                <label class="setup-field"><span>کد پستی <small>اختیاری</small></span><input type="text" name="postalCode" data-setup-field="postalCode" maxlength="20" inputmode="numeric" dir="ltr" autocomplete="postal-code"></label>
                <details class="setup-more setup-wide"><summary>تعداد پارکینگ و انباری (اختیاری)</summary><div class="setup-fields"><label class="setup-field"><span>تعداد پارکینگ</span><input type="number" name="parkingCount" data-setup-field="parkingCount" min="0" step="1"></label><label class="setup-field"><span>تعداد انباری</span><input type="number" name="storageCount" data-setup-field="storageCount" min="0" step="1"></label></div><p class="setup-note">این اعداد تعداد امکانات ساختمان‌اند؛ تخصیص آن‌ها به واحدها در این فرم انجام نمی‌شود.</p></details>
            </div>
        </section>

        <section data-setup-step="1" aria-labelledby="setup-floors-title" hidden>
            <div class="setup-section-heading"><span>گام دوم</span><h3 id="setup-floors-title" tabindex="-1">ساختمان چند طبقه دارد؟</h3><p>اگر ساختمان بلوک‌بندی ندارد، همان «بلوک اصلی» کافی است. تعداد واحدهای معمول هر طبقه را هم مشخص کنید.</p></div>
            <div data-setup-blocks></div>
            <button type="button" class="setup-button setup-button--soft" data-setup-add-block>+ افزودن بلوک دیگر</button>
        </section>

        <section data-setup-step="2" aria-labelledby="setup-units-title" hidden>
            <div class="setup-section-heading"><span>گام سوم</span><h3 id="setup-units-title" tabindex="-1">واحدها را بررسی کنید</h3><p>شماره واحدها خودکار پیشنهاد شده است. شماره، متراژ و تعداد واحدهای هر طبقه را می‌توانید تغییر دهید.</p></div>
            <div class="setup-totals" data-setup-totals></div>
            <div data-setup-units></div>
        </section>

        <section data-setup-step="3" aria-labelledby="setup-review-title" hidden>
            <div class="setup-section-heading"><span>گام آخر</span><h3 id="setup-review-title" tabindex="-1">همه‌چیز برای ثبت آماده است؟</h3><p>اطلاعات زیر را مرور کنید. با زدن «ثبت و راه‌اندازی»، ساختار ساختمان ذخیره می‌شود.</p></div>
            <div data-setup-review></div>
            <div class="setup-saving" role="status" aria-live="polite" data-setup-saving hidden><strong data-setup-saving-label></strong><progress max="100" value="0" data-setup-progress aria-label="پیشرفت ثبت ساختار"></progress><span data-setup-saving-count></span></div>
        </section>

        <footer class="setup-actions"><button type="button" class="setup-button setup-button--soft" data-setup-previous hidden>مرحله قبل</button><span data-setup-step-status aria-live="polite"></span><button type="submit" class="setup-button setup-button--primary" data-setup-next>ادامه</button></footer>
    </form>

    <section class="setup-card setup-success" data-setup-success hidden>
        <div class="setup-success__icon">@include('management.partials.icon', ['name' => 'check', 'size' => 30])</div><h3 tabindex="-1">ساختار ساختمان آماده شد</h3><p data-setup-success-summary></p>
        <div class="setup-success__actions"><a class="setup-button setup-button--primary" data-setup-invite hidden>دعوت ساکنین به واحدها</a><a class="setup-button setup-button--soft" href="{{ $setupConfig['unitsUrl'] }}">مشاهده واحدها</a><a class="setup-button setup-button--soft" href="{{ $setupConfig['dashboardUrl'] }}">بازگشت به داشبورد</a><button type="button" class="setup-button setup-button--soft" data-setup-another>راه‌اندازی ساختمان دیگر</button></div>
    </section>
    <noscript><p class="setup-error">برای استفاده از فرم مرحله‌ای، جاوااسکریپت مرورگر را فعال کنید.</p></noscript>
</div>
@endsection

@push('scripts')
    <script>window.BuildinoBuildingSetupConfig = {{ \Illuminate\Support\Js::from($setupConfig) }};</script>
    <script src="{{ asset('js/buildino-building-setup-engine.js') }}"></script>
    <script src="{{ asset('js/buildino-building-setup.js') }}"></script>
@endpush
