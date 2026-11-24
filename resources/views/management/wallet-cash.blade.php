@extends('management.layouts.app')
@section('title', 'دریافت و پرداخت | Buildino')
@section('page-title', 'دریافت و پرداخت')
@push('styles')<link rel="stylesheet" href="{{ asset('css/buildino-unit-ownerships.css') }}">@endpush
@section('content')
@php($money = fn ($v) => strtr(number_format((int) $v), ['0'=>'۰','1'=>'۱','2'=>'۲','3'=>'۳','4'=>'۴','5'=>'۵','6'=>'۶','7'=>'۷','8'=>'۸','9'=>'۹']))
<div class="ownership-page">
<h2>دریافت و پرداخت</h2>
@if(session('status'))<p role="status" class="alert alert-success">{{ session('status') }}</p>@endif
@if($errors->any())<div role="alert" class="alert alert-danger">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
@if($buildings->count() > 1)
<form method="GET" action="{{ route('management.wallet-cash.index') }}" class="ownership-card"><label class="ownership-field"><span>ساختمان</span><select name="building_id" required><option value="">انتخاب ساختمان</option>@foreach($buildings as $item)<option value="{{ $item->id }}" @selected($building?->id === $item->id)>{{ $item->title }}</option>@endforeach</select></label><button class="ownership-button" type="submit">نمایش</button></form>
@endif
@if($building)
<section class="ownership-card"><h3>{{ $building->title }}</h3><p>موجودی کیف پول ساختمان: {{ $money($wallet?->balance ?? 0) }} · قابل استفاده: {{ $money($wallet?->is_active ? $wallet->availableBalance() : 0) }} {{ $building->currency === 'IRT' ? 'تومان' : 'ریال' }}</p>
<p>دریافت، موجودی کیف پول انتخاب‌شده را افزایش می‌دهد؛ پرداخت از همان کیف پول کسر می‌شود. این فرم ثبت دریافت یا پرداخت انجام‌شده است و انتقال بانکی انجام نمی‌دهد.</p>
<form method="POST" enctype="multipart/form-data" action="{{ route('management.wallet-cash.store', $building) }}">@csrf
<input type="hidden" name="uuid" value="{{ old('uuid', (string) \Illuminate\Support\Str::uuid()) }}">
<div class="row g-3">
<div class="col-md-6"><label class="ownership-field"><span>نوع سند</span><select name="direction" required><option value="receipt" @selected(old('direction') === 'receipt')>دریافت / افزایش موجودی</option><option value="payment" @selected(old('direction') === 'payment')>پرداخت / برداشت</option></select></label></div>
<div class="col-md-6"><label class="ownership-field"><span>کیف پول مقصد عملیات</span><select name="unit_id"><option value="">کیف پول ساختمان</option>@foreach($units as $unit)<option value="{{ $unit->id }}" @selected((string) old('unit_id') === (string) $unit->id)>واحد {{ $unit->unit_number }} · طبقه {{ $unit->floor->floor_number }}</option>@endforeach</select></label></div>
<div class="col-md-6"><label class="ownership-field"><span>مبلغ ({{ $building->currency === 'IRT' ? 'تومان' : 'ریال' }})</span><input name="amount" inputmode="numeric" data-number-input value="{{ old('amount') }}" required></label></div>
<div class="col-md-6"><label class="ownership-field"><span>شماره سند دریافت / پرداخت</span><input name="document_number" maxlength="100" value="{{ old('document_number') }}" required></label></div>
<div class="col-md-6"><label class="ownership-field"><span>تصویر سند (اختیاری؛ JPG یا PNG تا ۵ مگابایت)</span><input type="file" name="image" accept=".jpg,.jpeg,.png"></label></div>
<div class="col-md-6"><label class="ownership-field"><span>شرح دریافت / پرداخت</span><textarea name="description" maxlength="2000">{{ old('description') }}</textarea></label></div>
</div><button type="submit" class="ownership-button ownership-button--primary">ثبت سند</button>
</form></section>
<section class="ownership-card"><h3>سوابق دریافت و پرداخت</h3><div class="table-responsive"><table class="table"><thead><tr><th>تاریخ</th><th>سند</th><th>نوع</th><th>کیف پول</th><th>مبلغ</th><th>شرح</th><th>ثبت‌کننده</th><th>تصویر</th></tr></thead><tbody>
@forelse($documents as $document)<tr><td>{{ \Morilog\Jalali\Jalalian::fromCarbon($document->created_at)->format('Y/m/d H:i') }}</td><td>{{ $document->document_number }}</td><td>{{ $document->direction === 'receipt' ? 'دریافت' : 'پرداخت' }}</td><td>{{ $document->unit ? 'واحد '.$document->unit->unit_number : 'ساختمان' }}</td><td>{{ $money($document->amount) }}</td><td>{{ $document->description }}</td><td>{{ $document->createdBy?->first_name }} {{ $document->createdBy?->last_name }}</td><td>@if($document->file_id)<a href="{{ route('management.wallet-cash.image', $document) }}" target="_blank" rel="noopener">دریافت تصویر</a>@else — @endif</td></tr>@empty<tr><td colspan="8">سندی ثبت نشده است.</td></tr>@endforelse
</tbody></table></div>{{ $documents->links() }}</section>
@elseif($buildings->isEmpty())<p>ساختمانی با دسترسی دریافت و پرداخت در اختیار شما نیست.</p>@else<p>ابتدا ساختمان را انتخاب کنید.</p>@endif
</div>
@endsection
