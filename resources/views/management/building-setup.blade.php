@extends('management.layouts.app')

@section('title', 'راه‌اندازی ساختمان')
@section('page-title', 'راه‌اندازی مجموعه')
@section('page-subtitle', 'اطلاعات ساختمان و واحدها را در یک مسیر ساده تکمیل کنید.')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/buildino-building-setup.css') }}?v={{ filemtime(public_path('css/buildino-building-setup.css')) }}">
@endpush

@section('content')
<div class="building-setup" data-building-setup>
    <header class="setup-intro">
        <div class="setup-intro__icon">@include('management.partials.icon', ['name' => 'building', 'size' => 28])</div>
        <div><span class="eyebrow">راه‌اندازی در ۳ گام</span><h2>مجموعه‌تان را آماده کنید</h2><p>اطلاعات ثبت‌نام شما آماده است؛ طبقات و واحدها را تکمیل و تأیید کنید.</p></div>
    </header>

    @if ($errors->any())<div class="setup-error" role="alert">{{ $errors->first() }}</div>@endif
    @foreach ($connectionReviews as $connection)
        <section class="setup-card setup-connection"><h3>درخواست اتصال به {{ $connection->complex->title }}</h3><p>{{ $connection->building?->title }} — {{ $connection->building?->address }}</p><p class="setup-note">با تأیید، این بلوک زیرمجموعه مجتمع شما می‌شود. مدیر بلوک فقط دسترسی همان بلوک را حفظ می‌کند.</p>
            <form method="POST" action="{{ route('management.building-setup.review', $connection) }}">@csrf<button class="setup-button setup-button--primary" name="decision" value="approved">تأیید اتصال</button> <button class="setup-button setup-button--soft" name="decision" value="rejected">رد درخواست</button></form>
        </section>
    @endforeach
    @foreach ($setupConfig['complexes'] as $complex)
        <details class="setup-card setup-connection"><summary>اتصال مدیران بلوک به {{ $complex['title'] }}</summary><p>کد مجتمع را فقط به مدیر بلوک موردنظر بدهید؛ اتصال پس از تأیید شما انجام می‌شود.</p><strong dir="ltr">{{ $complex['code'] }}</strong></details>
    @endforeach
    @foreach ($setupConfig['buildings'] as $building)
        @if ($building['complexType'] === 'unlinked' && $building['canUpdate'])
            @php $pendingConnection = $connections->first(fn ($item) => $item->building_id === $building['id'] && $item->status === 'pending'); @endphp
            <details class="setup-card setup-connection" data-connection-building="{{ $building['id'] }}"><summary>اتصال {{ $building['title'] }} به مجتمع</summary><p class="setup-note">تکمیل طبقات و واحدها به تأیید اتصال وابسته نیست. تا پیش از تأیید، بلوک شما مستقل از دسترسی مجتمع مدیریت می‌شود.</p>
                @if ($pendingConnection)
                    <p role="status">درخواست شما در انتظار تأیید مدیر مجتمع است.</p><form method="POST" action="{{ route('management.building-setup.review', $pendingConnection) }}">@csrf<button class="setup-button setup-button--soft" name="decision" value="cancelled">لغو درخواست</button></form>
                @else
                    @php $lastConnection = $connections->firstWhere('building_id', $building['id']); @endphp
                    @if ($lastConnection)<p>درخواست قبلی: {{ $lastConnection->status === 'rejected' ? 'رد شده' : 'لغو شده' }}</p>@endif
                    <form method="POST" action="{{ route('management.building-setup.connect', $building['id']) }}">@csrf<label class="setup-field"><span>کد مجتمع دریافتی از مدیر مجتمع</span><input name="complex_code" required maxlength="100" dir="ltr" autocomplete="off"></label><button class="setup-button setup-button--soft">ارسال درخواست اتصال</button></form>
                @endif
            </details>
        @endif
    @endforeach

    <div class="setup-resume" data-setup-resume hidden><span>پیش‌نویس راه‌اندازی قبلی شما بازیابی شد.</span><button type="button" data-setup-reset>شروع راه‌اندازی تازه</button></div>
    <nav class="setup-steps" aria-label="مراحل راه‌اندازی" @if ($setupConfig['completedSetup']) hidden @endif>
        @foreach (['مجموعه و نشانی', 'طبقات', 'واحدها و ثبت'] as $title)
            <button type="button" data-setup-step-link="{{ $loop->index }}" @if ($loop->first) aria-current="step" @endif><span>{{ ['۱', '۲', '۳'][$loop->index] }}</span><strong>{{ $title }}</strong></button>
        @endforeach
    </nav>

    <form class="setup-card" data-setup-form novalidate @if ($setupConfig['completedSetup']) hidden @endif>
        <div class="setup-error" role="alert" data-setup-error hidden></div>
        <section data-setup-step="0" aria-labelledby="setup-building-title">
            <div class="setup-section-heading"><span>گام اول</span><h3 id="setup-building-title" tabindex="-1">کدام ساختمان یا بلوک را تکمیل می‌کنید؟</h3><p>برای ادامه ثبت‌نام، همان مورد انتخاب‌شده را تکمیل کنید.</p></div>
            <div class="setup-choice-grid" role="group" aria-label="روش شروع" @if (! $setupConfig['canCreateBuilding']) hidden @endif>
                <label class="setup-choice" @if ($setupConfig['buildings']->isEmpty()) hidden @endif><input type="radio" name="mode" value="existing" data-setup-field="mode"><span><strong>ادامه راه‌اندازی مورد موجود</strong><small>طبقات و واحدها را تکمیل می‌کنم</small></span></label>
                <label class="setup-choice" @if (! $setupConfig['canCreateBuilding']) hidden @endif><input type="radio" name="mode" value="new" data-setup-field="mode"><span><strong>{{ $setupConfig['canCreateComplex'] ? 'ساختمان یا بلوک جدید' : 'بلوک جدید مجتمع' }}</strong><small>طبقات و واحدهای آن را تعریف می‌کنم</small></span></label>
            </div>
            <div data-setup-existing>
                @if ($setupConfig['buildings']->count() === 1)
                    <div class="setup-selected-building" data-setup-selected-building>
                        <span class="setup-selected-building__icon">@include('management.partials.icon', ['name' => 'check', 'size' => 20])</span>
                        <span><strong>{{ $setupConfig['buildings']->first()['title'] }}</strong><small>{{ $setupConfig['buildings']->first()['structureType'] === 'standalone' ? 'ساختمان مستقل' : ($setupConfig['buildings']->first()['complexTitle'] ?: 'محدوده انتخاب‌شده شما') }}</small></span>
                        <span class="setup-selected-building__status">انتخاب شد</span>
                    </div>
                @endif
                <label class="setup-field" @if ($setupConfig['buildings']->count() === 1) hidden @endif><span>ساختمان شما</span><select name="buildingId" data-setup-field="buildingId" data-required="1" data-placeholder="ساختمان را انتخاب کنید"><option value="">ساختمان را انتخاب کنید</option>@foreach ($setupConfig['buildings'] as $building)<option value="{{ $building['id'] }}">{{ $building['title'] }}@if ($building['complexTitle']) — {{ $building['complexTitle'] }}@endif</option>@endforeach</select></label>
                <p class="setup-note" data-setup-context></p>
                <div class="setup-fields setup-wide" data-setup-existing-details hidden>
                    <label class="setup-field setup-wide"><span data-setup-existing-title-label>نام ساختمان</span><input name="existingTitle" data-setup-field="existingTitle" maxlength="255"></label>
                    <label class="setup-field setup-wide"><span>نشانی</span><textarea name="existingAddress" data-setup-field="existingAddress" rows="2" maxlength="2000"></textarea></label>
                    <label class="setup-field"><span>کد پستی</span><input name="existingPostalCode" data-setup-field="existingPostalCode" maxlength="20" inputmode="numeric" dir="ltr"></label>
                    <label class="setup-field"><span>تعداد پارکینگ</span><input name="existingParkingCount" data-setup-field="existingParkingCount" type="number" min="0" step="1"></label>
                    <label class="setup-field"><span>تعداد انباری</span><input name="existingStorageCount" data-setup-field="existingStorageCount" type="number" min="0" step="1"></label>
                </div>
                @if ($setupConfig['buildings']->isEmpty())<p class="setup-note">هنوز ساختمانی برای انتخاب ندارید. از گزینه ساختمان جدید شروع کنید.</p>@endif
            </div>
            <div class="setup-fields" data-setup-new hidden>
                <label class="setup-field setup-wide"><span data-setup-new-title-label>نام ساختمان</span><input type="text" name="title" data-setup-field="title" maxlength="255" placeholder="مثلاً ساختمان سرو" autocomplete="organization"></label>
                <label class="setup-field setup-wide"><span>این ساختمان در یک مجتمع قرار دارد؟</span><select name="complexMode" data-setup-field="complexMode" data-required="1">
                    @if ($setupConfig['canCreateComplex'])<option value="standalone">ساختمان مستقل</option><option value="new">بلوک یک مجتمع جدید</option>@endif
                    @if ($setupConfig['complexes']->isNotEmpty())<option value="existing">بلوک یک مجتمع تحت مدیریت من</option>@endif
                </select></label>
                <label class="setup-field setup-wide" data-setup-complex-name hidden><span>نام مجتمع</span><input type="text" name="complexTitle" data-setup-field="complexTitle" maxlength="255" placeholder="مثلاً مجتمع بهار"></label>
                <label class="setup-field setup-wide" data-setup-complex-existing hidden><span>مجتمع</span><select name="complexId" data-setup-field="complexId" data-required="1" data-placeholder="مجتمع را انتخاب کنید"><option value="">مجتمع را انتخاب کنید</option>@foreach ($setupConfig['complexes'] as $complex)<option value="{{ $complex['id'] }}">{{ $complex['title'] }}</option>@endforeach</select></label>
                <div class="setup-fields setup-wide" data-setup-location>
                    <label class="setup-field"><span>استان</span><select name="provinceId" data-setup-field="provinceId" data-required="1" data-placeholder="انتخاب استان"><option value="">انتخاب استان</option>@foreach ($setupConfig['locations'] as $province)<option value="{{ $province['id'] }}">{{ $province['name'] }}</option>@endforeach</select></label>
                    <label class="setup-field"><span>شهر</span><select name="cityId" data-setup-field="cityId" data-required="1" data-placeholder="انتخاب شهر"><option value="">ابتدا استان را انتخاب کنید</option></select></label>
                </div>
                <details class="setup-more setup-wide" data-setup-optional><summary>نشانی، کد پستی و امکانات <small>اختیاری</small></summary><div class="setup-fields"><label class="setup-field setup-wide"><span>نشانی</span><textarea name="address" data-setup-field="address" rows="2" maxlength="2000" placeholder="خیابان، کوچه و پلاک" autocomplete="street-address"></textarea></label><label class="setup-field"><span>کد پستی</span><input type="text" name="postalCode" data-setup-field="postalCode" maxlength="20" inputmode="numeric" dir="ltr" autocomplete="postal-code"></label><label class="setup-field"><span>تعداد پارکینگ</span><input type="number" name="parkingCount" data-setup-field="parkingCount" min="0" step="1"></label><label class="setup-field"><span>تعداد انباری</span><input type="number" name="storageCount" data-setup-field="storageCount" min="0" step="1"></label></div><p class="setup-note">پارکینگ و انباری فقط به‌صورت تعداد ساختمان ثبت می‌شوند.</p></details>
            </div>
        </section>

        <section data-setup-step="1" aria-labelledby="setup-floors-title" hidden>
            <div class="setup-section-heading"><span>گام دوم</span><h3 id="setup-floors-title" tabindex="-1">چند طبقه و چند واحد در هر طبقه دارید؟</h3><p>تعداد طبقات، همکف و زیرزمین را مشخص کنید؛ شماره واحدها خودکار پیشنهاد می‌شود.</p></div>
            <div class="setup-structure-summary" data-setup-structure-summary aria-live="polite"></div>
            <div data-setup-blocks></div>
            <button type="button" class="setup-button setup-button--soft" data-setup-add-block hidden>+ افزودن بخش به ساختار قبلی</button>
        </section>

        <section data-setup-step="2" aria-labelledby="setup-units-title" hidden>
            <div class="setup-section-heading"><span>گام سوم</span><h3 id="setup-units-title" tabindex="-1">واحدهای پیشنهادی را بررسی کنید</h3><p>واحدها در پیش‌نویس آماده‌اند. برای اصلاح شماره‌ها، کارت طبقه را باز کنید. پس از بررسی واحدها، «ثبت و راه‌اندازی» را بزنید.</p></div>
            <div class="setup-totals" data-setup-totals></div>
            <div data-setup-units></div>
            <details class="setup-more"><summary>خلاصه اطلاعات ساختمان</summary><div data-setup-review></div></details>
            <div class="setup-saving" role="status" aria-live="polite" data-setup-saving hidden><strong data-setup-saving-label></strong><progress max="100" value="0" data-setup-progress aria-label="پیشرفت ثبت ساختار"></progress><span data-setup-saving-count></span></div>
        </section>

        <footer class="setup-actions"><button type="button" class="setup-button setup-button--soft" data-setup-previous hidden>مرحله قبل</button><span data-setup-step-status aria-live="polite"></span><button type="submit" class="setup-button setup-button--primary" data-setup-next>ادامه</button></footer>
    </form>

    <section class="setup-card setup-success" data-setup-success hidden>
        <div class="setup-success__icon">@include('management.partials.icon', ['name' => 'check', 'size' => 30])</div><h3 tabindex="-1">ساختار آماده شد</h3><p data-setup-success-summary></p>
        <div class="setup-success__actions"><a class="setup-button setup-button--soft" href="{{ route('management.user-import.index') }}">ورود کاربران و ارتباط واحد از اکسل</a><a class="setup-button setup-button--primary" data-setup-ownership hidden>ثبت مالکیت واحدها</a><a class="setup-button setup-button--soft" data-setup-invite hidden>دعوت ساکنین به واحدها</a><a class="setup-button setup-button--soft" data-setup-unit-link href="{{ $setupConfig['unitsUrl'] }}">مشاهده واحدها</a><a class="setup-button setup-button--soft" href="{{ $setupConfig['dashboardUrl'] }}">بازگشت به داشبورد</a><button type="button" class="setup-button setup-button--soft" data-setup-another>راه‌اندازی ساختمان دیگر</button></div>
    </section>
    <noscript><p class="setup-error">برای استفاده از فرم مرحله‌ای، جاوااسکریپت مرورگر را فعال کنید.</p></noscript>
</div>
@endsection

@push('scripts')
    <script>window.BuildinoBuildingSetupConfig = {{ \Illuminate\Support\Js::from($setupConfig) }};</script>
    <script src="{{ asset('js/buildino-building-setup-engine.js') }}?v={{ filemtime(public_path('js/buildino-building-setup-engine.js')) }}"></script>
    <script src="{{ asset('js/buildino-building-setup.js') }}?v={{ filemtime(public_path('js/buildino-building-setup.js')) }}"></script>
@endpush
