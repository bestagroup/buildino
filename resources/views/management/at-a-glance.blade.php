@extends('management.layouts.app')
@section('title', 'در یک نگاه | Buildino')
@section('page-title', 'در یک نگاه')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/buildino-at-a-glance.css') }}?v={{ filemtime(public_path('css/buildino-at-a-glance.css')) }}">
@endpush
@section('content')
<div class="glance">
    <header class="glance-hero"><div><span>گزارش مدیریتی ساختمان</span><h1>در یک نگاه</h1><p>تصویر امروز از واحدها، افراد و حساب‌های ساختمان</p></div><span>به‌روزرسانی: {{ now()->format('Y/m/d H:i') }}</span></header>
    <form method="GET" action="{{ route('management.at-a-glance') }}" class="glance-toolbar">
        <label for="glance-building">ساختمان</label>
        <select id="glance-building" name="building_id" required>
            @foreach($buildings as $item)<option value="{{ $item->id }}" @selected($building?->id === $item->id)>{{ $item->title }}</option>@endforeach
        </select><button type="submit" class="button button-primary">نمایش گزارش</button>
    </form>
    @if(!$report)
        <p class="glance-note">ساختمانی با دسترسی مشاهده واحدها، مالکین، ساکنین و گزارش مالی برای شما وجود ندارد.</p>
    @else
        @php
            $money = fn ($amount) => number_format((int) $amount);
            $currency = $report['currency'] === 'IRR' ? 'ریال' : $report['currency'];
            $totals = $report['totals'];
            $period = $report['period'];
        @endphp
        <section class="glance-stats" aria-label="خلاصه ساختمان">
            @foreach([
                ['کل واحدها', $totals['units'], 'واحد'],
                ['دارای سکونت ثبت‌شده', $totals['occupied'], 'واحد'],
                ['جمع نفرات ثبت‌شده', $totals['people'], 'نفر'],
                ['مانده بدهی قطعی', $totals['debt'], $currency],
                ['اعتبار قابل استفاده واحدها', $totals['available'], $currency],
                ['خالص بدهی / اعتبار', $totals['net'], $currency],
            ] as [$label, $value, $suffix])
                <article class="glance-stat"><span>{{ $label }}</span><strong dir="ltr">{{ $money($value) }}</strong><small>{{ $suffix }}</small></article>
            @endforeach
        </section>
        <div class="glance-note">
            <strong>آخرین دوره: {{ $period?->title ?? 'هنوز محاسبه‌ای ثبت نشده' }}</strong>
            @if($period)<span> · تا {{ $period->period_end?->format('Y/m/d') }} · {{ ['calculated' => 'محاسبه‌شده، غیرقطعی', 'issued' => 'صادرشده', 'closed' => 'بسته‌شده'][$period->status->value] ?? '' }}</span>@endif
            <p>بدهی قطعی، مجموع مانده صورتحساب‌های صادرشده تمام دوره‌ها پس از پرداخت است؛ پیش‌نویس و صورتحساب لغوشده در آن محاسبه نمی‌شوند. مبلغ آخرین دوره ستون مستقلی دارد و دوباره به بدهی اضافه نمی‌شود.</p>
            <p>خالص = بدهی قطعی منهای اعتبار قابل استفاده کیف پول واحد. مقدار منفی نشان‌دهنده مازاد اعتبار است؛ این نمایش به معنی تسویه یا برداشت خودکار نیست. کیف پول شخصی مالک و ساکن در این گزارش لحاظ نمی‌شود.</p>
        </div>
        @forelse($report['floors'] as $floorRows)
            @php($floor = $floorRows->first()['floor'])
            <section class="glance-floor">
                <header><h2>{{ $floor->block->title }} · {{ $floor->title ?: 'طبقه '.$floor->floor_number }}</h2><span>{{ $floorRows->count() }} واحد · {{ $floorRows->sum('people') }} نفر</span></header>
                <div class="glance-scroll" tabindex="0" role="region" aria-label="جدول واحدهای طبقه {{ $floor->floor_number }}">
                    <table><caption class="visually-hidden">وضعیت واحدها و حساب‌ها؛ مبالغ به {{ $currency }}</caption><thead><tr><th scope="col">واحد / وضعیت</th><th scope="col">مالکین فعلی</th><th scope="col">ساکنین فعلی</th><th scope="col">نفرات</th><th scope="col">کیف پول ({{ $currency }})</th><th scope="col">مبلغ آخرین دوره</th><th scope="col">بدهی قطعی</th><th scope="col">خالص / وضعیت مالی</th></tr></thead><tbody>
                    @foreach($floorRows as $row)
                        <tr>
                            <th scope="row"><strong>واحد {{ $row['unit']->unit_number }}</strong><small>{{ !$row['unit']->is_active ? 'غیرفعال' : ($row['occupied'] ? 'دارای ساکن' : 'بدون سکونت ثبت‌شده') }}</small></th>
                            <td>@forelse($row['owners'] as $name)<span class="glance-person">{{ $name }}</span>@empty<span class="glance-muted">ثبت نشده</span>@endforelse<a href="{{ route('management.operations.show', ['resource' => 'ownerships', 'unit_id' => $row['unit']->id]) }}">مالکیت</a></td>
                            <td>@forelse($row['residents'] as $name)<span class="glance-person">{{ $name }}</span>@empty<span class="glance-muted">ثبت نشده</span>@endforelse<a href="{{ route('management.operations.show', ['resource' => 'occupancies', 'unit_id' => $row['unit']->id]) }}">سکونت</a></td>
                            <td>{{ $money($row['people']) }}</td>
                            <td><strong>{{ $money($row['balance']) }}</strong><small>مسدود: {{ $money($row['locked']) }}</small><small>قابل استفاده: {{ $money($row['available']) }}</small>@if($row['walletInactive'])<small>کیف پول غیرفعال</small>@endif</td>
                            <td>{{ $row['latest'] === null ? '—' : $money($row['latest']) }}</td>
                            <td>{{ $money($row['debt']) }}</td>
                            <td><span class="glance-status {{ $row['net'] > 0 ? 'glance-debt' : ($row['net'] < 0 ? 'glance-credit' : '') }}">{{ $row['net'] > 0 ? 'مانده بدهی' : ($row['net'] < 0 ? 'مازاد اعتبار' : 'متوازن') }}</span><strong dir="ltr">{{ $money(abs($row['net'])) }}</strong></td>
                        </tr>
                    @endforeach
                    </tbody></table>
                </div>
            </section>
        @empty
            <p class="glance-note">هنوز واحدی برای این ساختمان ثبت نشده است.</p>
        @endforelse
    @endif
</div>
@endsection
