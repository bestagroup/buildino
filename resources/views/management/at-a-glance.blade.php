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
    <header class="ownership-hero"><div class="ownership-hero__icon" aria-hidden="true">@include('management.partials.icon', ['name' => 'building', 'size' => 27])</div><div><h2>در یک نگاه</h2><p>وضعیت واحدها، ساکنان و حساب‌های ساختمان</p></div>@if($report)<span class="glance-updated">به‌روزرسانی: {{ $date($report['generatedAt'], true) }}</span>@endif</header>
    <section class="ownership-card"><div class="ownership-card__heading"><div><h3>ساختمان</h3><p>گزارش ساختمان موردنظر را انتخاب کنید.</p></div></div><form method="GET" action="{{ route('management.at-a-glance') }}" class="glance-toolbar"><label class="ownership-field" for="glance-building"><span>انتخاب ساختمان</span><select id="glance-building" name="building_id" data-select2="off" @disabled($buildings->isEmpty())>@forelse($buildings as $item)<option value="{{ $item->id }}" @selected($building?->id === $item->id)>{{ $item->title }}</option>@empty<option value="">ساختمانی در دسترس نیست</option>@endforelse</select></label><button type="submit" class="ownership-button ownership-button--primary" @disabled($buildings->isEmpty())>نمایش و به‌روزرسانی</button></form></section>
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
                <div class="glance-filters" data-glance-filters hidden><label class="ownership-field"><span>جست‌وجو</span><input type="search" data-glance-search placeholder="شماره واحد، نام مالک یا ساکن"></label><label class="ownership-field"><span>بلوک</span><select data-glance-block data-select2="off"><option value="">همه بلوک‌ها</option>@foreach($blocks as $block)<option value="{{ $block->id }}">{{ $block->title }}</option>@endforeach</select></label><label class="ownership-field"><span>وضعیت</span><select data-glance-status data-select2="off"><option value="">همه واحدها</option><option value="shortfall">دارای کسری اعتبار</option><option value="debt">دارای مانده صورتحساب</option><option value="surplus">دارای مازاد اعتبار</option><option value="vacant">بدون سکونت ثبت‌شده</option><option value="inactive">واحد غیرفعال</option></select></label><button type="button" class="ownership-button" data-glance-reset>پاک‌کردن فیلترها</button></div>
                <p class="glance-result" role="status" aria-live="polite" data-glance-result>{{ $number($totals['units']) }} واحد</p>
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
                            <article class="glance-unit" data-glance-unit data-block="{{ $floor->block_id }}" data-shortfall="{{ $row['shortfall'] }}" data-debt="{{ $row['debt'] }}" data-surplus="{{ $row['surplus'] }}" data-occupied="{{ $row['occupied'] ? '1' : '0' }}" data-active="{{ $unit->is_active ? '1' : '0' }}" data-search="{{ $unit->unit_number.' '.$unit->title.' '.$row['owners']->implode(' ').' '.$row['residents']->implode(' ') }}">
                                <header><div><h5>واحد {{ $unit->unit_number }}</h5>@if($unit->title)<span>{{ $unit->title }}</span>@endif</div><div class="glance-unit-badges">@if(!$unit->is_active)<span class="glance-badge">غیرفعال</span>@endif<span class="glance-badge" data-occupied="{{ $row['occupied'] ? '1' : '0' }}">{{ $row['occupied'] ? 'دارای سکونت' : 'بدون سکونت ثبت‌شده' }}</span><span class="glance-people">{{ $number($row['people']) }} نفر</span></div></header>
                                <dl class="glance-person-grid"><div><dt>مالکین فعلی</dt><dd>@forelse($row['owners'] as $name)<span>{{ $name }}</span>@empty<span class="glance-muted">ثبت نشده</span>@endforelse</dd></div><div><dt>ساکنین فعلی</dt><dd>@forelse($row['residents'] as $name)<span>{{ $name }}</span>@empty<span class="glance-muted">ثبت نشده</span>@endforelse</dd></div></dl>
                                <dl class="glance-unit-metrics"><div><dt>مانده صورتحساب</dt><dd>{{ $number($row['debt']) }}</dd></div><div><dt>اعتبار قابل استفاده</dt><dd>{{ $number($row['available']) }}</dd></div><div class="{{ $row['shortfall'] > 0 ? 'glance-shortfall' : '' }}"><dt>کسری اعتبار</dt><dd>{{ $number($row['shortfall']) }}</dd></div></dl><p class="glance-currency">مبالغ به {{ $currency }}@if($row['surplus'] > 0) · مازاد اعتبار: {{ $number($row['surplus']) }} {{ $currency }}@endif</p>
                                <div class="glance-latest"><div><span>آخرین دوره</span><small>{{ $latestLabel }}</small></div><strong>{{ $row['latest'] === null ? '—' : $number($row['latest']).' '.$currency }}</strong></div>
                                <details class="glance-wallet"><summary>جزئیات کیف پول</summary><dl><dt>موجودی کل</dt><dd>{{ $number($row['balance']) }} {{ $currency }}</dd><dt>مبلغ مسدودشده</dt><dd>{{ $number($row['locked']) }} {{ $currency }}</dd></dl>@if($row['walletInactive'])<p>کیف پول غیرفعال است؛ موجودی آن در اعتبار قابل استفاده محاسبه نشده است.</p>@endif</details>
                                <footer><a class="ownership-button" href="{{ route('management.operations.show', ['resource'=>'ownerships', ...$unitContext]) }}">مالکیت</a><a class="ownership-button" href="{{ route('management.operations.show', ['resource'=>'occupancies', ...$unitContext]) }}">سکونت</a>@if($canViewInvoices)<a class="ownership-button" href="{{ route('management.operations.show', ['resource'=>'invoices', ...$unitContext]) }}">صورتحساب‌ها</a>@endif</footer>
                            </article>
                        @endforeach</div>
                    </section>
                @endforeach
            </section>
        @else
            <section class="ownership-card"><p class="ownership-empty">هنوز واحدی برای این ساختمان ثبت نشده است.</p></section>
        @endif
    @endif
</div>
@endsection
@push('scripts')
<script src="{{ asset('js/buildino-at-a-glance.js') }}?v={{ filemtime(public_path('js/buildino-at-a-glance.js')) }}" defer></script>
@endpush
