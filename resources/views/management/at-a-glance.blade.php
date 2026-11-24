@extends('management.layouts.app')
@section('title', 'در یک نگاه | Buildino')
@section('page-title', 'در یک نگاه')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/buildino-unit-ownerships.css') }}?v={{ filemtime(public_path('css/buildino-unit-ownerships.css')) }}">
<link rel="stylesheet" href="{{ asset('css/buildino-at-a-glance.css') }}?v={{ filemtime(public_path('css/buildino-at-a-glance.css')) }}">
@endpush
@section('content')
@php
    $digits = ['0'=>'۰','1'=>'۱','2'=>'۲','3'=>'۳','4'=>'۴','5'=>'۵','6'=>'۶','7'=>'۷','8'=>'۸','9'=>'۹',','=>'٬','-'=>'−'];
    $number = fn ($value) => strtr(number_format((int) $value), $digits);
    $date = fn ($value, $time = false) => $value ? strtr(\Morilog\Jalali\Jalalian::fromCarbon($value)->format($time ? 'Y/m/d H:i' : 'Y/m/d'), $digits) : '—';
@endphp
<div class="ownership-page glance-page" data-glance-page>
    <nav class="glance-breadcrumb" aria-label="مسیر صفحه"><a href="{{ route('management.dashboard') }}">داشبورد</a><span>/</span><strong>در یک نگاه</strong></nav>
    <header class="ownership-hero glance-hero"><div class="glance-hero__copy"><h2>در یک نگاه</h2><p>وضعیت واحدها، ساکنان و حساب‌های ساختمان</p>@if($report)<span class="glance-updated">به‌روزرسانی: {{ $date($report['generatedAt'], true) }}</span>@endif</div><div class="glance-hero__art" aria-hidden="true">@include('management.partials.icon', ['name' => 'building', 'size' => 64])</div></header>
    <section class="ownership-card glance-building"><div class="ownership-card__heading"><div><h3>ساختمان</h3></div></div><form method="GET" action="{{ route('management.at-a-glance') }}" class="glance-toolbar"><label class="ownership-field" for="glance-building"><span>انتخاب ساختمان</span><select id="glance-building" name="building_id" @disabled($buildings->isEmpty())>@forelse($buildings as $item)<option value="{{ $item->id }}" @selected($building?->id === $item->id)>{{ $item->title }}</option>@empty<option value="">ساختمانی در دسترس نیست</option>@endforelse</select></label><button type="submit" class="ownership-button ownership-button--primary" @disabled($buildings->isEmpty())>نمایش و به‌روزرسانی</button></form></section>
    @if(!$report)
        <section class="ownership-card"><p class="ownership-empty">ساختمانی با دسترسی هم‌زمان به واحدها، مالکیت، سکونت و گزارش مالی برای شما وجود ندارد.</p></section>
    @else
        @php
            $totals = $report['totals']; $period = $report['period'];
            $currency = ['IRR'=>'ریال', 'IRT'=>'تومان'][$report['currency']] ?? $report['currency'];
            $blocks = $report['rows']->map(fn ($row) => $row['floor']->block)->unique('id')->values();
            $periodCalculated = $period?->status === \App\Enums\ChargePeriodStatus::Calculated;
            $periodStatus = $period ? ['calculated'=>'محاسبه‌شده · غیرقطعی','issued'=>'صادرشده','closed'=>'بسته‌شده'][$period->status->value] : '';
        @endphp
        <section class="glance-stats" aria-label="خلاصه تمام واحدهای ساختمان">
            @foreach([
                ['واحدهای ساختمان', $totals['units'], 'واحد'],
                ['دارای سکونت ثبت‌شده', $totals['occupied'], 'واحد'],
                ['نفرات واحدهای دارای سکونت', $totals['people'], 'نفر'],
                ['مانده صورتحساب‌های صادرشده', $totals['debt'], $currency],
                ['مانده بستانکاری واحدها', $totals['credit'], $currency],
                ['اعتبار قابل استفاده واحدها', $totals['available'], $currency],
                ['کسری اعتبار واحدها', $totals['shortfall'], $currency],
            ] as [$label, $value, $suffix])
                <article class="glance-stat"><span>{{ $label }}</span><strong>{{ $number($value) }} <small>{{ $suffix }}</small></strong></article>
            @endforeach
        </section>
        <section class="ownership-card glance-period"><div><h3>{{ $period ? $period->title : 'آخرین دوره شارژ' }}</h3><p>@if($period)<span class="glance-badge">{{ $periodStatus }}</span> {{ $date($period->period_start) }} تا {{ $date($period->period_end) }}@else هنوز دوره محاسبه‌شده یا صادرشده‌ای ثبت نشده است.@endif</p></div>@if($canViewPeriods)<a class="ownership-button" href="{{ route('management.operations.show', ['resource'=>'charge-periods','building_id'=>$building->id]) }}">دوره‌های شارژ</a>@endif</section>
        <details class="glance-explanation"><summary>مبنای اعداد گزارش</summary><p>مانده بدهی از صورتحساب‌های صادرشده تمام دوره‌ها محاسبه می‌شود؛ پیش‌نویس و صورتحساب لغوشده در آن نیست. مبلغ آخرین دوره جداست و دوباره به بدهی اضافه نمی‌شود.</p><p>اعتبار قابل استفاده فقط مربوط به کیف پول همان واحد است و مبلغ مسدودشده یا کیف پول غیرفعال در آن نیست. کسری هر واحد جداگانه محاسبه می‌شود؛ اعتبار یک واحد بدهی واحد دیگر را نمی‌پوشاند. این گزارش پرداخت یا تسویه خودکار انجام نمی‌دهد.</p></details>
        @if($report['rows']->isNotEmpty())
            <section class="ownership-card glance-directory" aria-labelledby="glance-directory-title"><div class="ownership-card__heading"><div><h3 id="glance-directory-title">واحدها</h3><p>خلاصه بالا مربوط به تمام واحدهای ساختمان است؛ فیلترها فقط فهرست زیر را تغییر می‌دهند.</p></div></div>
                <div class="glance-filters" data-glance-filters hidden><label class="ownership-field"><span>جست‌وجو</span><input type="search" data-glance-search placeholder="شماره واحد، نام مالک یا ساکن"></label><label class="ownership-field"><span>بلوک</span><select data-glance-block><option value="">همه بلوک‌ها</option>@foreach($blocks as $block)<option value="{{ $block->id }}">{{ $block->title }}</option>@endforeach</select></label><label class="ownership-field"><span>وضعیت</span><select data-glance-status><option value="">همه واحدها</option><option value="shortfall">دارای کسری اعتبار</option><option value="debt">دارای مانده صورتحساب</option><option value="surplus">دارای مازاد اعتبار</option><option value="vacant">بدون سکونت ثبت‌شده</option><option value="inactive">واحد غیرفعال</option></select></label><button type="button" class="ownership-button" data-glance-reset>پاک‌کردن فیلترها</button></div>
                <p class="glance-result" role="status" aria-live="polite" data-glance-result>{{ $number($totals['units']) }} واحد</p>
                <p class="ownership-alert ownership-alert--error" data-glance-refresh-error role="alert" hidden>به‌روزرسانی انجام نشد. <button type="button" class="ownership-button" data-glance-refresh>تلاش دوباره</button></p>
                <p class="ownership-empty" data-glance-empty hidden>واحدی با این جست‌وجو یا وضعیت پیدا نشد.</p>
                @foreach($report['floors'] as $floorRows)
                    @php
                        $floor = $floorRows->first()['floor'];
                    @endphp
                    <section class="glance-floor" data-glance-floor><header><div><h4>{{ $floor->block->title }} · {{ $floor->title ?: 'طبقه '.$number($floor->floor_number) }}</h4><span><b data-glance-floor-count>{{ $number($floorRows->count()) }}</b> واحد</span></div><a class="glance-link" href="{{ route('management.operations.show', ['resource'=>'units','building_id'=>$building->id,'block_id'=>$floor->block_id,'floor_id'=>$floor->id]) }}">مدیریت واحدهای طبقه</a></header>
                        <div class="glance-units">@foreach($floorRows as $row)
                            @php
                                $unit = $row['unit'];
                                $unitContext = ['building_id'=>$building->id, 'unit_id'=>$unit->id];
                                $latestLabel = ['invoice'=>$periodCalculated ? 'صورتحساب غیرقطعی دوره' : 'مبلغ صورتحساب دوره','calculation'=>'محاسبه‌شده، غیرقطعی','excluded'=>'صورتحساب معتبر ندارد','none'=>'مبلغی در این دوره ثبت نشده'][$row['latestSource']];
                            @endphp
                            <article class="glance-unit-row" data-glance-unit data-unit-id="{{ $unit->id }}" data-unit-title="واحد {{ strtr($unit->unit_number, $digits) }}" data-block="{{ $floor->block_id }}" data-shortfall="{{ $row['shortfall'] }}" data-debt="{{ $row['debt'] }}" data-surplus="{{ $row['surplus'] }}" data-occupied="{{ $row['occupied'] ? '1' : '0' }}" data-active="{{ $unit->is_active ? '1' : '0' }}" data-search="{{ $unit->unit_number.' '.$unit->title.' '.$row['owners']->implode(' ').' '.$row['residents']->implode(' ') }}">
                                <button type="button" class="glance-unit-open" data-glance-open aria-haspopup="dialog" aria-controls="glance-unit-dialog">
                                    <span class="glance-unit-identity"><strong>واحد {{ strtr($unit->unit_number, $digits) }}</strong><small>{{ $unit->title ?: ($row['residents']->first() ?: $row['owners']->first() ?: 'بدون نام ثبت‌شده') }}</small></span>
                                    <span class="glance-badge" data-occupied="{{ $row['occupied'] ? '1' : '0' }}">{{ !$unit->is_active ? 'غیرفعال' : ($row['occupied'] ? 'دارای سکونت' : 'بدون سکونت') }}</span>
                                    <span class="glance-row-amount {{ $row['shortfall'] > 0 ? 'glance-row-amount--due' : '' }}"><small>قابل پرداخت</small><strong>{{ $number($row['shortfall']) }} <small>{{ $currency }}</small></strong></span>
                                    <span class="glance-row-chevron" aria-hidden="true">@include('management.partials.icon', ['name'=>'chevron','size'=>18])</span>
                                </button>
                                <template data-glance-detail>
                                <header><div><h5>واحد {{ strtr($unit->unit_number, $digits) }}</h5>@if($unit->title)<span>{{ $unit->title }}</span>@endif</div><div class="glance-unit-badges">@if(!$unit->is_active)<span class="glance-badge">غیرفعال</span>@endif<span class="glance-badge" data-occupied="{{ $row['occupied'] ? '1' : '0' }}">{{ $row['occupied'] ? 'دارای سکونت' : 'بدون سکونت ثبت‌شده' }}</span><span class="glance-people">{{ $number($row['people']) }} نفر</span></div></header>
                                <div class="glance-payable {{ $row['shortfall'] > 0 ? 'glance-payable--due' : '' }}"><div><span>قابل پرداخت</span><small>پس از کسر اعتبار و بستانکاری</small></div><strong>{{ $number($row['shortfall']) }} <small>{{ $currency }}</small></strong></div>
                                <dl class="glance-person-grid"><div><dt>@include('management.partials.icon', ['name'=>'users','size'=>18]) مالکین فعلی</dt><dd>@forelse($row['owners'] as $name)<span>{{ $name }}</span>@empty<span class="glance-muted">ثبت نشده</span>@endforelse</dd></div><div><dt>@include('management.partials.icon', ['name'=>'building','size'=>18]) ساکنین فعلی</dt><dd>@forelse($row['residents'] as $name)<span>{{ $name }}</span>@empty<span class="glance-muted">ثبت نشده</span>@endforelse</dd></div></dl>
                                <section class="glance-financial" aria-label="خلاصه مالی واحد"><h4>وضعیت حساب <small>مبالغ به {{ $currency }}</small></h4><dl class="glance-unit-metrics"><div><dt>مانده صورتحساب</dt><dd>{{ $number($row['debt']) }}</dd></div><div><dt>اعتبار قابل استفاده</dt><dd>{{ $number($row['available']) }}</dd></div><div><dt>مانده بستانکاری</dt><dd>{{ $number($row['credit']) }}</dd></div></dl>@if($row['surplus'] > 0)<p class="glance-surplus">مازاد اعتبار: {{ $number($row['surplus']) }} {{ $currency }}</p>@endif</section>
                                <div class="glance-latest"><div><span>آخرین دوره</span><small>{{ $latestLabel }}</small></div><strong>{{ $row['latest'] === null ? '—' : $number($row['latest']).' '.$currency }}</strong></div>
                                <details class="glance-wallet"><summary>جزئیات کیف پول</summary><dl><dt>موجودی کل</dt><dd>{{ $number($row['balance']) }} {{ $currency }}</dd><dt>مبلغ مسدودشده</dt><dd>{{ $number($row['locked']) }} {{ $currency }}</dd></dl>@if($row['walletInactive'])<p>کیف پول غیرفعال است؛ موجودی آن در اعتبار قابل استفاده محاسبه نشده است.</p>@endif</details>
                                <footer><a class="ownership-button" href="{{ route('management.operations.show', ['resource'=>'ownerships', ...$unitContext]) }}">مالکیت</a><a class="ownership-button" href="{{ route('management.operations.show', ['resource'=>'occupancies', ...$unitContext]) }}">سکونت</a>@if($canViewInvoices)<a class="ownership-button" href="{{ route('management.unit-statements', ['unit'=>$unit->id, ...$unitContext]) }}">صورتحساب‌ها</a>@endif</footer>
                                </template>
                            </article>
                        @endforeach</div>
                    </section>
                @endforeach
            </section>
            <dialog class="glance-dialog" id="glance-unit-dialog" data-glance-dialog aria-labelledby="glance-unit-title">
                <div class="glance-dialog-shell">
                    <header class="glance-dialog-heading"><div><small>{{ $building->title }}</small><h3 id="glance-unit-title" tabindex="-1"></h3></div><button type="button" class="ownership-button" data-glance-close aria-label="بستن جزئیات واحد"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m6 6 12 12M6 18 18 6"/></svg></button></header>
                    <nav class="glance-dialog-tabs" aria-label="بخش‌های واحد"><button type="button" class="ownership-button" data-glance-section="overview" aria-current="page">خلاصه</button><button type="button" class="ownership-button" data-glance-section="ownerships">مالکیت</button><button type="button" class="ownership-button" data-glance-section="occupancies">سکونت</button>@if($canViewInvoices)<button type="button" class="ownership-button" data-glance-section="invoices">صورتحساب‌ها</button>@endif</nav>
                    <div class="glance-dialog-body">
                        <p class="ownership-alert ownership-alert--error" role="alert" data-glance-modal-refresh-error hidden>اطلاعات خلاصه به‌روز نشد. <button type="button" class="ownership-button" data-glance-refresh>تلاش دوباره</button></p>
                        <article class="glance-unit-detail" data-glance-overview></article>
                        <div class="glance-workspace" data-glance-workspace hidden><p role="status" data-glance-loading>در حال بارگذاری…</p><div class="ownership-alert ownership-alert--error" role="alert" data-glance-frame-error hidden>بارگذاری انجام نشد. <button type="button" class="ownership-button" data-glance-retry>تلاش دوباره</button></div><div data-glance-frames></div></div>
                    </div>
                    <div class="glance-discard" role="alert" data-glance-discard hidden><p>تغییرات ثبت‌نشده کنار گذاشته شوند؟</p><div><button type="button" class="ownership-button" data-glance-keep>ادامه ویرایش</button><button type="button" class="ownership-button ownership-button--danger" data-glance-leave>خروج بدون ذخیره</button></div></div>
                </div>
            </dialog>
        @else
            <section class="ownership-card"><p class="ownership-empty">هنوز واحدی برای این ساختمان ثبت نشده است.</p></section>
        @endif
    @endif
</div>
@endsection
@push('scripts')
<script src="{{ asset('js/buildino-at-a-glance.js') }}?v={{ filemtime(public_path('js/buildino-at-a-glance.js')) }}" defer></script>
@endpush
