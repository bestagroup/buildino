@extends($manager ? (request()->boolean('glance_embed') ? 'management.layouts.unit-workspace' : 'management.layouts.app') : 'portal.layouts.app')
@section('title', 'صورتحساب‌های ماهانه | Buildino')
@section('page-title', 'صورتحساب‌های ماهانه')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/buildino-unit-ownerships.css') }}">
@endpush
@section('content')
@php($money = fn ($value) => strtr(number_format((int) $value), ['0'=>'۰','1'=>'۱','2'=>'۲','3'=>'۳','4'=>'۴','5'=>'۵','6'=>'۶','7'=>'۷','8'=>'۸','9'=>'۹']))
<div class="ownership-page">
<h2>صورتحساب‌های ماهانه</h2>
@if(session('status'))<div class="alert alert-success" role="status">{{ session('status') }}</div>@endif
@if($errors->any())<div class="alert alert-danger" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
@foreach($units as $item)
<p>{{ $item->floor->block->building->title }} · واحد {{ $item->unit_number }} · موجودی قابل استفاده کیف پول: {{ $money($balances[$item->id]) }} {{ $item->floor->block->building->currency === 'IRT' ? 'تومان' : 'ریال' }}</p>
@endforeach
@if(!$manager)<a class="ownership-button" href="{{ route('portal.resident.operations.index', ['resource'=>'wallet']) }}">شارژ کیف پول واحد</a>@endif
<div class="table-responsive">
<table class="table"><thead><tr><th>ساختمان / واحد</th><th>دوره</th><th>جمع صورتحساب</th><th>پرداخت‌شده</th><th>مانده بدهی</th><th>جزئیات</th></tr></thead><tbody>
@forelse($statements as $statement)
<tr><td>{{ $statement['building']->title }} / {{ $statement['unit']->unit_number }}</td><td>{{ $statement['title'] }}</td><td>{{ $money($statement['total']) }}</td><td>{{ $money($statement['paid']) }}</td><td>{{ $money($statement['outstanding']) }}</td><td>
<details><summary>مشاهده</summary>
<div class="table-responsive"><table class="table"><caption>{{ $statement['title'] }} · مبالغ به {{ $statement['building']->currency === 'IRT' ? 'تومان' : 'ریال' }}</caption><thead><tr><th>عنوان</th><th>تعداد</th><th>مبلغ واحد</th><th>مبلغ کل</th></tr></thead><tbody>
@foreach($statement['items'] as $line)<tr><td>{{ $line->title }}</td><td>{{ $line->quantity }}</td><td>{{ $money($line->unit_amount) }}</td><td>{{ $money($line->total_amount) }}</td></tr>@endforeach
@if($statement['discount'])<tr><td>تخفیف</td><td>۱</td><td>{{ $money(-$statement['discount']) }}</td><td>{{ $money(-$statement['discount']) }}</td></tr>@endif
@if($statement['penalty'])<tr><td>جریمه</td><td>۱</td><td>{{ $money($statement['penalty']) }}</td><td>{{ $money($statement['penalty']) }}</td></tr>@endif
@if($statement['adjustment'])<tr><td>تعدیل بدهی / طلب</td><td>۱</td><td>{{ $money($statement['adjustment']) }}</td><td>{{ $money($statement['adjustment']) }}</td></tr>@endif
@if($statement['credit'])<tr><td>کسر اعتبار بستانکاری</td><td>۱</td><td>{{ $money(-$statement['credit']) }}</td><td>{{ $money(-$statement['credit']) }}</td></tr>@endif
@if($statement['consumed_credit'])<tr><td>بستانکاری مصرف‌شده در تسویه اسناد (جلوگیری از کسر مجدد)</td><td>۱</td><td>{{ $money($statement['consumed_credit']) }}</td><td>{{ $money($statement['consumed_credit']) }}</td></tr>@endif
</tbody><tfoot><tr><th colspan="3">جمع کل</th><td>{{ $money($statement['total']) }}</td></tr><tr><th colspan="3">پرداخت‌شده</th><td>{{ $money($statement['paid']) }}</td></tr><tr><th colspan="3">مانده قابل پرداخت</th><td>{{ $money($statement['outstanding']) }}</td></tr></tfoot></table></div>
@if(!$manager && $statement['outstanding'] > 0)
<form method="POST" action="{{ route('portal.resident.statements.settle', ['invoice'=>$statement['id']]) }}">@csrf<button type="submit" class="ownership-button ownership-button--primary">تسویه از کیف پول واحد</button></form>
@endif
</details></td></tr>
@empty<tr><td colspan="6">صورتحساب صادرشده‌ای وجود ندارد.</td></tr>@endforelse
</tbody></table></div>
@if($canDeposit)
<section class="ownership-card"><h3>ثبت واریزی به کیف پول واحد</h3>
<form method="POST" enctype="multipart/form-data" action="{{ route('management.unit-deposits', ['unit'=>$units->first()->id, 'glance_embed'=>request()->boolean('glance_embed') ? 1 : null]) }}">@csrf
<input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', (string) \Illuminate\Support\Str::uuid()) }}">
<label class="ownership-field"><span>مبلغ واریزی</span><input name="amount" inputmode="numeric" data-number-input value="{{ old('amount') }}" required></label>
<label class="ownership-field"><span>شماره رسید / پیگیری</span><input name="reference" maxlength="100" value="{{ old('reference') }}" required></label>
<label class="ownership-field"><span>سند واریز (تصویر یا PDF؛ حداکثر ۵ مگابایت)</span><input type="file" name="receipt" accept=".pdf,.jpg,.jpeg,.png" required></label>
<label class="ownership-field"><span>توضیحات</span><textarea name="description" maxlength="2000">{{ old('description') }}</textarea></label>
<button class="ownership-button ownership-button--primary" type="submit">ثبت واریزی و افزایش موجودی واحد</button>
</form></section>
@endif
@if($manager && $deposits->isNotEmpty())
<section class="ownership-card"><h3>سوابق افزایش موجودی واحد</h3><div class="table-responsive"><table class="table"><thead><tr><th>تاریخ</th><th>مبلغ</th><th>شرح / شماره رسید</th><th>وضعیت</th><th>سند</th></tr></thead><tbody>
@foreach($deposits as $deposit)<tr><td>{{ $deposit->credited_at ? \Morilog\Jalali\Jalalian::fromCarbon($deposit->credited_at)->format('Y/m/d H:i') : '—' }}</td><td>{{ $money($deposit->amount) }}</td><td>{{ $deposit->payment?->description }}</td><td>{{ $deposit->status === \App\Enums\WalletTopUpStatus::Credited ? 'ثبت‌شده' : 'در انتظار تأیید' }}</td><td>@foreach($deposit->payment?->fileRelations ?? [] as $relation)@if($relation->file)<a href="{{ route('api.v1.files.download', $relation->file) }}" target="_blank" rel="noopener">مشاهده سند</a>@endif @endforeach</td></tr>@endforeach
</tbody></table></div></section>
@endif
</div>
@endsection
