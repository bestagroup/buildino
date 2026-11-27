@extends($manager ? (request()->boolean('glance_embed') ? 'management.layouts.unit-workspace' : 'management.layouts.app') : 'portal.layouts.app')
@section('title', 'صورتحساب‌های ماهانه | Buildino')
@section('page-title', 'صورتحساب‌های ماهانه')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/buildino-unit-ownerships.css') }}">
@if($manager)<link rel="stylesheet" href="{{ asset('css/buildino-unit-statements.css') }}?v={{ filemtime(public_path('css/buildino-unit-statements.css')) }}">@endif
@endpush
@push('portal-theme')
<link rel="stylesheet" href="{{ asset('css/buildino-unit-statements.css') }}?v={{ filemtime(public_path('css/buildino-unit-statements.css')) }}">
@endpush
@section('content')
@php
    $digits = ['0'=>'۰','1'=>'۱','2'=>'۲','3'=>'۳','4'=>'۴','5'=>'۵','6'=>'۶','7'=>'۷','8'=>'۸','9'=>'۹',','=>'٬','-'=>'−'];
    $money = fn ($value) => strtr(number_format((int) $value), $digits);
    $currency = fn ($building) => $building->currency === 'IRT' ? 'تومان' : 'ریال';
    $embedded = $manager && request()->boolean('glance_embed');
@endphp
<div class="ownership-page statement-page" data-statement-page @if(session('status')) data-statement-saved @endif>
    @unless($embedded)<header class="statement-heading"><div><h2>صورتحساب‌های ماهانه</h2><span class="statement-muted">{{ $manager ? 'حساب واحد' : 'حساب واحدهای من' }}</span></div>@if(!$manager)<a class="ownership-button" href="{{ route('portal.resident.operations.index', ['resource'=>'wallet', ...request()->only('unit_id')]) }}">شارژ کیف پول واحد</a>@endif</header>@endunless
    @if(session('status'))<p class="statement-notice" role="status">{{ session('status') }}</p>@endif
    @if($errors->any())<div class="statement-notice statement-notice--error" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
    @if(!$manager && $statementUnits->count() > 1)
    <form method="GET" class="statement-filter"><label class="ownership-field" for="statement-unit"><span>واحد</span><select id="statement-unit" name="unit_id"><option value="">همه واحدهای من</option>@foreach($statementUnits as $item)<option value="{{ $item->id }}" @selected(request()->integer('unit_id') === $item->id)>{{ $item->floor->block->building->title }} · واحد {{ strtr($item->unit_number, $digits) }}</option>@endforeach</select></label><button type="submit" class="ownership-button">نمایش</button></form>
    @endif
    <div class="statement-accounts">
        @foreach($units as $item)
        @php($unitStatements = $statements->where('unit.id', $item->id))
        <section class="statement-account" aria-label="حساب واحد {{ strtr($item->unit_number, $digits) }}">
            <div class="statement-account__identity"><span class="statement-account__icon" aria-hidden="true">@include('management.partials.icon', ['name'=>'building', 'size'=>22])</span><div><strong>واحد {{ strtr($item->unit_number, $digits) }}</strong><span>{{ $item->floor->block->building->title }}</span></div></div>
            <div class="statement-account__amount"><span>موجودی قابل استفاده کیف پول</span><strong>{{ $money($balances[$item->id]) }} <small>{{ $currency($item->floor->block->building) }}</small></strong></div>
            <div class="statement-account__amount"><span>مانده صورتحساب‌ها</span><strong>{{ $money($unitStatements->sum('outstanding')) }} <small>{{ $currency($item->floor->block->building) }}</small></strong></div>
            @if($canDeposit)<button type="button" class="ownership-button ownership-button--primary" data-statement-deposit aria-controls="statement-deposit-dialog" aria-haspopup="dialog">ثبت واریزی</button>@endif
        </section>
        @endforeach
    </div>
    <section class="statement-panel" aria-labelledby="statement-list-title">
        <header class="statement-heading"><h3 id="statement-list-title">دوره‌های صورتحساب</h3><span class="statement-muted">{{ $money($statements->count()) }} دوره</span></header>
        <div class="statement-list">
        @forelse($statements as $statement)
            @php($unitCurrency = $currency($statement['building']))
            <details class="statement-record">
                <summary><span class="statement-record__title"><strong>{{ $statement['title'] }}</strong><small>{{ $statement['building']->title }} · واحد {{ strtr($statement['unit']->unit_number, $digits) }}</small></span><span class="statement-value"><small>جمع صورتحساب</small><strong>{{ $money($statement['total']) }} <small>{{ $unitCurrency }}</small></strong></span><span class="statement-value"><small>پرداخت‌شده</small><strong>{{ $money($statement['paid']) }} <small>{{ $unitCurrency }}</small></strong></span><span class="statement-value" @if($statement['outstanding'] > 0) data-due @endif><small>مانده قابل پرداخت</small><strong>{{ $money($statement['outstanding']) }} <small>{{ $unitCurrency }}</small></strong></span><span class="statement-disclosure">جزئیات <span aria-hidden="true">⌄</span></span></summary>
                <div class="statement-record__body">
                    <div class="statement-lines"><table><caption>ریز هزینه‌های {{ $statement['title'] }} · {{ $unitCurrency }}</caption><thead><tr><th scope="col">عنوان</th><th scope="col">تعداد</th><th scope="col">مبلغ واحد</th><th scope="col">مبلغ کل</th></tr></thead><tbody>
                    @foreach($statement['items'] as $line)<tr><td data-label="عنوان">{{ $line->title }}</td><td data-label="تعداد">{{ strtr((string) $line->quantity, $digits) }}</td><td data-label="مبلغ واحد">{{ $money($line->unit_amount) }}</td><td data-label="مبلغ کل">{{ $money($line->total_amount) }}</td></tr>@endforeach
                    </tbody></table></div>
                    <dl class="statement-totals">
                        @foreach(['discount'=>['تخفیف',-1], 'penalty'=>['جریمه',1], 'adjustment'=>['تعدیل بدهی / طلب',1], 'credit'=>['کسر اعتبار بستانکاری',-1], 'consumed_credit'=>['بستانکاری مصرف‌شده در تسویه اسناد',1]] as $key=>[$label,$sign])
                            @if($statement[$key])<div><dt>{{ $label }}</dt><dd>{{ $money($sign * $statement[$key]) }} {{ $unitCurrency }}</dd></div>@endif
                        @endforeach
                        <div><dt>جمع کل</dt><dd>{{ $money($statement['total']) }} {{ $unitCurrency }}</dd></div><div><dt>پرداخت‌شده</dt><dd>{{ $money($statement['paid']) }} {{ $unitCurrency }}</dd></div><div class="statement-totals__final"><dt>مانده قابل پرداخت</dt><dd>{{ $money($statement['outstanding']) }} {{ $unitCurrency }}</dd></div>
                    </dl>
                    @if(!$manager && $statement['outstanding'] > 0)
                    <form method="POST" action="{{ route('portal.resident.statements.settle', ['invoice'=>$statement['id']]) }}" data-statement-settle data-confirm="{{ $statement['title'].' · واحد '.strtr($statement['unit']->unit_number, $digits).' · '.$money($statement['outstanding']).' '.$unitCurrency }}">@csrf<p class="statement-notice statement-notice--error" role="alert" data-settle-error hidden></p><div class="statement-actions"><button type="submit" class="ownership-button ownership-button--primary">تسویه از کیف پول واحد</button></div></form>
                    @endif
                </div>
            </details>
        @empty<div class="statement-empty">@include('management.partials.icon', ['name'=>'wallet','size'=>32])<h3>صورتحساب صادرشده‌ای وجود ندارد.</h3><p>پس از صدور صورتحساب، جزئیات هر دوره اینجا نمایش داده می‌شود.</p></div>@endforelse
        </div>
    </section>
    @if($canDeposit)
    <dialog class="statement-dialog" id="statement-deposit-dialog" aria-labelledby="statement-deposit-title" @if($errors->any()) data-reopen @endif>
        <header class="statement-heading"><h3 id="statement-deposit-title">ثبت واریزی به کیف پول واحد</h3><button type="button" class="ownership-button" data-deposit-close aria-label="بستن ثبت واریزی">×</button></header>
        <form method="POST" enctype="multipart/form-data" action="{{ route('management.unit-deposits', ['unit'=>$units->first()->id, 'glance_embed'=>$embedded ? 1 : null]) }}" data-deposit-form>@csrf
            <input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', (string) \Illuminate\Support\Str::uuid()) }}">
            @if($errors->any())<div class="statement-notice statement-notice--error" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
            <div class="statement-form-grid">
                <label class="ownership-field"><span>مبلغ واریزی ({{ $currency($units->first()->floor->block->building) }}) <b aria-hidden="true">*</b></span><input name="amount" inputmode="numeric" data-number-input value="{{ old('amount') }}" required min="1" max="999999999999" aria-required="true"></label>
                <label class="ownership-field"><span>شماره رسید / پیگیری <b aria-hidden="true">*</b></span><input name="reference" maxlength="100" value="{{ old('reference') }}" required aria-required="true"></label>
                <label class="ownership-field statement-wide"><span>سند واریز <b aria-hidden="true">*</b></span><input type="file" name="receipt" accept=".pdf,.jpg,.jpeg,.png" required aria-describedby="statement-receipt-help"><small id="statement-receipt-help">تصویر یا PDF؛ حداکثر ۵ مگابایت</small></label>
                <label class="ownership-field statement-wide"><span>توضیحات</span><textarea name="description" maxlength="2000" rows="3">{{ old('description') }}</textarea></label>
            </div><footer class="statement-actions"><button type="button" class="ownership-button" data-deposit-close>انصراف</button><button class="ownership-button ownership-button--primary" type="submit">ثبت واریزی و افزایش موجودی واحد</button></footer>
        </form>
    </dialog>
    @endif
    @if($manager && $deposits->isNotEmpty())
    <section class="statement-panel"><header class="statement-heading"><h3>سوابق افزایش موجودی واحد</h3></header><div class="statement-deposits">
        @foreach($deposits as $deposit)<article><div><strong>{{ $money($deposit->amount) }} {{ $currency($units->first()->floor->block->building) }}</strong><small>{{ $deposit->credited_at ? strtr(\Morilog\Jalali\Jalalian::fromCarbon($deposit->credited_at)->format('Y/m/d H:i'), $digits) : '—' }}</small></div><p>{{ $deposit->payment?->description }}</p><span>{{ $deposit->status === \App\Enums\WalletTopUpStatus::Credited ? 'ثبت‌شده' : 'در انتظار تأیید' }}</span><div>@foreach($deposit->payment?->fileRelations ?? [] as $relation)@if($relation->file)<a class="ownership-button" href="{{ route('api.v1.files.download', $relation->file) }}" target="_blank" rel="noopener">مشاهده سند</a>@endif @endforeach</div></article>@endforeach
    </div></section>
    @endif
</div>
@endsection
@push('scripts')
<script src="{{ asset('js/buildino-unit-statements.js') }}?v={{ filemtime(public_path('js/buildino-unit-statements.js')) }}" defer></script>
@endpush
