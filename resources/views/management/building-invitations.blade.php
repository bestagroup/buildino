@extends('management.layouts.app')
@section('title', 'دعوت کاربران | Buildino')
@section('page-title', 'دعوت کاربران ساختمان')
@section('page-subtitle', 'ارسال دستی دعوت و پیگیری استفاده از کد')
@section('content')
<div data-building-invitations @if($building) data-send-url="{{ route('management.building-invitations.send', $building) }}" @endif>
@if($buildings->count() > 1)
<form method="GET" class="card p-3 mb-3">
<label for="invitation-building">ساختمان</label>
<select id="invitation-building" name="building_id" class="form-select" required><option value="">انتخاب ساختمان</option>@foreach($buildings as $item)<option value="{{ $item->id }}" @selected($building?->id === $item->id)>{{ $item->title }}</option>@endforeach</select>
<button class="btn btn-primary mt-2">نمایش کاربران و دعوت‌ها</button>
</form>
@endif
@if($building)
<h2>{{ $building->title }}</h2>
<p>هر کاربر برای هر واحد دعوت مستقلی دریافت می‌کند. لینک ثبت‌نام: <a href="{{ route('register') }}" dir="ltr">{{ route('register') }}</a></p>
<label for="invitation-hours">اعتبار کد جدید (ساعت)</label><input id="invitation-hours" type="number" class="form-control mb-3" min="1" max="720" value="72" required>
<section class="card p-3 mb-3">
<h3>مخاطبان ساختمان</h3>
@if($canCreate)<label><input type="checkbox" data-select-page> انتخاب همه مخاطبان قابل ارسال در این صفحه</label>@endif
<div class="table-responsive"><table class="table"><thead><tr><th>انتخاب</th><th>واحد</th><th>نام و نام خانوادگی</th><th>موبایل</th><th>نتیجه ارسال</th></tr></thead><tbody>
@forelse($recipients as $person)
<tr><td><input type="checkbox" data-recipient data-unit="{{ $person->unit_id }}" data-user="{{ $person->user_id }}" aria-label="انتخاب {{ $person->first_name }} {{ $person->last_name }} واحد {{ $person->unit_number }}" @disabled(!$canCreate || !$person->is_active || $person->is_blocked || !$person->mobile)></td><td>{{ $person->unit_number }}</td><td>{{ $person->first_name }} {{ $person->last_name }}</td><td dir="ltr">{{ $person->mobile }}</td><td data-row-result>{{ !$person->is_active || $person->is_blocked || !$person->mobile ? 'حساب غیرفعال یا فاقد موبایل' : 'آماده انتخاب' }}</td></tr>
@empty<tr><td colspan="5">مالک یا ساکن فعالی برای این ساختمان ثبت نشده است.</td></tr>@endforelse
</tbody></table></div>
{{ $recipients->links() }}
@if($canCreate)<button type="button" class="btn btn-primary" data-send-selected>ارسال دعوت‌نامه به انتخاب‌شده‌ها</button>@endif
</section>
<div role="status" aria-live="polite" data-send-result></div>
<a href="{{ route('management.operations.show', ['resource' => 'invitations', 'building_id' => $building->id]) }}" class="btn btn-outline-primary mb-3" data-refresh-invitations>به‌روزرسانی فهرست دعوت‌ها</a>
<section class="card p-3" id="invitation-history"><h3>سوابق ارسال و استفاده از کد</h3>
<div class="table-responsive"><table class="table"><thead><tr><th>مخاطب</th><th>واحد</th><th>وضعیت ارسال / کد</th><th>آخرین ارسال</th><th>انقضا</th><th>زمان استفاده</th><th>عملیات</th></tr></thead><tbody>
@forelse($invitations as $invitation)
@php($used = $invitation->accepted_at !== null || $invitation->status->value === 'accepted')
<tr><td>{{ trim(($invitation->invitedUser?->first_name ?? '').' '.($invitation->invitedUser?->last_name ?? '')) ?: $invitation->mobile }}</td><td>{{ $invitation->unit?->unit_number }}</td><td>{{ $used ? 'استفاده‌شده' : ($invitation->status->value === 'cancelled' ? 'لغوشده' : ($invitation->isExpired() ? 'منقضی‌شده' : ($invitation->status->value === 'sent' ? 'ارسال‌شده؛ استفاده‌نشده' : 'ارسال تأیید نشده؛ استفاده‌نشده'))) }}</td><td>{{ $invitation->sent_at?->format('Y/m/d H:i') ?? '—' }}</td><td>{{ $invitation->expires_at?->format('Y/m/d H:i') ?? '—' }}</td><td>{{ $invitation->accepted_at?->format('Y/m/d H:i') ?? '—' }}</td><td>
@if($canUpdate && !$used && $invitation->status->value !== 'cancelled' && $invitation->invited_user_id)<button type="button" class="btn btn-outline-primary" data-resend="{{ $invitation->id }}" data-unit="{{ $invitation->unit_id }}" data-user="{{ $invitation->invited_user_id }}">ارسال مجدد</button>@endif
<span data-row-result></span></td></tr>
@empty<tr><td colspan="7">دعوتی ثبت نشده است.</td></tr>@endforelse
</tbody></table></div>{{ $invitations->links() }}
</section>
@else<p>برای مشاهده مخاطبان، ساختمان را انتخاب کنید.</p>@endif
</div>
@endsection
@push('scripts')
<script src="{{ asset('js/buildino-building-invitations.js') }}?v={{ filemtime(public_path('js/buildino-building-invitations.js')) }}" defer></script>
@endpush
