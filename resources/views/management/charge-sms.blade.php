@extends('management.layouts.app')
@section('title','ارسال پیامک شارژ دوره')
@section('content')
<div class="ownership-page"><h2>ارسال پیامک شارژ دوره</h2>
@if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
@if(!$ready)<div class="alert alert-warning">ارسال تا تنظیم قالب تأییدشده پیامک شارژ در قاصدک غیرفعال است.</div>@endif
<form method="GET" class="ownership-card"><div class="row g-3">
@if($buildings->count()!==1)<label class="col-md-6">ساختمان<select class="form-select" name="building_id" onchange="this.form.querySelector('[name=period_id]').value='';this.form.submit()"><option value="">انتخاب ساختمان</option>@foreach($buildings as $item)<option value="{{ $item->id }}" @selected($building?->id===$item->id)>{{ $item->title }}</option>@endforeach</select></label>@else<input type="hidden" name="building_id" value="{{ $building?->id }}">@endif
<label class="col-md-6">دوره شارژ<select class="form-select" name="period_id"><option value="">انتخاب دوره</option>@foreach($periods as $item)<option value="{{ $item->id }}" @selected($period?->id===$item->id)>{{ $item->title }}</option>@endforeach</select></label>
</div><button class="btn btn-primary">نمایش</button></form>
<p>فقط دوره‌های دارای صورتحساب صادرشده نمایش داده می‌شوند. مبلغ، مجموع بدهی محاسبه‌شده صورتحساب‌های این دوره برای هر شخص است. هر شخص در هر ساختمان و دوره یک بار پیام دریافت می‌کند؛ انقضای زمانی ندارد.</p>
@if($period)
@foreach($recipients['unresolved'] as $unit)
<form class="ownership-card" method="POST" action="{{ route('management.charge-sms.assign',$period) }}">@csrf<input type="hidden" name="unit_id" value="{{ $unit->id }}">
<label>تعیین مخاطب صورتحساب واحد {{ $unit->unit_number }}<select class="form-select" name="user_id" required><option value="">انتخاب مالک یا ساکن فعال</option>@foreach($choices[$unit->id] as $candidate)<option value="{{ $candidate->id }}">{{ $candidate->first_name }} {{ $candidate->last_name }} — {{ $candidate->mobile }}</option>@endforeach</select></label><button class="btn btn-secondary" @disabled($history->isNotEmpty())>ثبت مخاطب</button>
</form>
@endforeach
@if($recipients['unresolved'])<div class="alert alert-warning">ابتدا مخاطب واحدهای بالا را مشخص کنید؛ سپس ارسال فعال می‌شود.</div>@endif
<section class="ownership-card" id="charge-sms-panel" data-send-url="{{ route('management.charge-sms.send',$period) }}">
<label><input type="checkbox" id="charge-select-all"> انتخاب همه مخاطبان قابل ارسال</label>
<div class="table-responsive"><table class="table"><thead><tr><th>انتخاب</th><th>نام</th><th>موبایل</th><th>واحدها</th><th>بدهی دوره</th><th>وضعیت</th></tr></thead><tbody>
@forelse($recipients['rows'] as $row)
<tr data-recipient="{{ $row['user_id'] }}"><td><input type="checkbox" class="charge-recipient" value="{{ $row['user_id'] }}" @disabled(!$ready || $recipients['unresolved'] || !$row['eligible'] || $row['notice'])></td><td>{{ $row['user']?->first_name }} {{ $row['user']?->last_name }}</td><td>{{ $row['user']?->mobile }}</td><td>{{ implode('، ',$row['units']) }}</td><td>{{ number_format($row['amount']) }} ریال</td><td data-send-status>{{ $row['notice'] ? 'قبلاً درخواست ارسال ثبت شده' : ($row['eligible']?'آماده ارسال':'حساب یا موبایل نامعتبر') }}</td></tr>
@empty<tr><td colspan="6">مخاطبی با بدهی محاسبه‌شده وجود ندارد.</td></tr>@endforelse
</tbody></table></div><button type="button" class="btn btn-primary" id="charge-send" @disabled(!$ready || $recipients['unresolved'])>ارسال پیامک به انتخاب‌شده‌ها</button><p id="charge-result" role="status" aria-live="polite"></p>
</section>
<section class="ownership-card"><h3>سوابق ارسال دوره</h3><div class="table-responsive"><table class="table"><thead><tr><th>مخاطب</th><th>موبایل</th><th>مبلغ زمان ارسال</th><th>وضعیت</th><th>تاریخ درخواست</th><th>تاریخ پذیرش</th></tr></thead><tbody>
@forelse($history as $notice)<tr><td>{{ $notice->user?->first_name }} {{ $notice->user?->last_name }}</td><td>{{ $notice->mobile }}</td><td>{{ number_format($notice->amount) }} {{ $notice->currency==='IRT'?'تومان':'ریال' }}</td><td>{{ ['accepted'=>'پذیرفته‌شده توسط قاصدک','processing'=>'در حال ارسال / نیازمند بررسی','unknown'=>'نتیجه نامشخص؛ بررسی در قاصدک'][$notice->status] ?? $notice->status }}</td><td>{{ \Morilog\Jalali\Jalalian::fromCarbon($notice->created_at)->format('Y/m/d H:i') }}</td><td>{{ $notice->sent_at ? \Morilog\Jalali\Jalalian::fromCarbon($notice->sent_at)->format('Y/m/d H:i') : '—' }}</td></tr>@empty<tr><td colspan="6">ارسالی ثبت نشده است.</td></tr>@endforelse
</tbody></table></div><p>پذیرش توسط قاصدک به معنی تحویل قطعی به گوشی نیست. درخواست نامشخص برای جلوگیری از پیام تکراری خودکار تکرار نمی‌شود.</p></section>
@endif
</div>
@endsection
@push('scripts')
<script src="{{ asset('js/buildino-charge-sms.js') }}?v=1" defer></script>
@endpush
