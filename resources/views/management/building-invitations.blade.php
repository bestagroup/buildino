@extends('management.layouts.app')
@section('title', 'دعوت کاربران | Buildino')
@section('page-title', 'دعوت کاربران')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/buildino-building-invitations.css') }}?v={{ filemtime(public_path('css/buildino-building-invitations.css')) }}">
@endpush
@section('content')
@php
    $digits = static fn ($value) => strtr((string) $value, array_combine(range(0, 9), ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹']));
@endphp
<div class="invitation-page" data-building-invitations @if($building) data-send-url="{{ route('management.building-invitations.send', $building) }}" @endif>
@if($buildings->count() > 1)
<form method="GET" class="invitation-panel invitation-location">
    <div><label for="invitation-building">ساختمان</label><select id="invitation-building" name="building_id" class="form-select" required><option value="">انتخاب ساختمان</option>@foreach($buildings as $item)<option value="{{ $item->id }}" @selected($building?->id === $item->id)>{{ $item->title }}</option>@endforeach</select></div>
    <button type="submit" class="btn btn-primary">نمایش</button>
</form>
@endif
@if($building)
<div class="invitation-context"><span>ساختمان</span><strong>{{ $building->title }}</strong></div>
<section class="invitation-panel" aria-labelledby="invitation-recipients-title">
    <header class="invitation-heading"><div><h2 id="invitation-recipients-title">مخاطبان</h2><span class="invitation-count">{{ $digits($recipients->total()) }} مخاطب</span></div>@if($canCreate)<label class="invitation-select-all"><input type="checkbox" data-select-page>انتخاب همه این صفحه</label>@endif</header>
    <div class="invitation-table-wrap"><table class="invitation-table">
        <caption class="visually-hidden">مخاطبان ساختمان {{ $building->title }}</caption>
        <thead><tr><th scope="col" class="invitation-check"><span class="visually-hidden">انتخاب</span></th><th scope="col">مخاطب</th><th scope="col">واحد</th><th scope="col">وضعیت</th></tr></thead>
        <tbody>
        @forelse($recipients as $person)
            @php
                $unavailable = !$person->is_active || $person->is_blocked || !$person->mobile;
            @endphp
            <tr>
                <td class="invitation-check"><input type="checkbox" data-recipient data-unit="{{ $person->unit_id }}" data-user="{{ $person->user_id }}" aria-label="انتخاب {{ $person->first_name }} {{ $person->last_name }} واحد {{ $digits($person->unit_number) }}" @disabled(!$canCreate || $unavailable)></td>
                <td data-label="مخاطب"><div class="invitation-person"><strong>{{ trim($person->first_name.' '.$person->last_name) ?: 'بدون نام' }}</strong><bdi dir="ltr">{{ $digits($person->mobile ?: '—') }}</bdi></div></td>
                <td data-label="واحد"><span class="invitation-unit">{{ $digits($person->unit_number) }}</span></td>
                <td data-label="وضعیت"><span class="invitation-state" data-row-result data-state="{{ $unavailable ? 'muted' : 'ready' }}">{{ $person->is_blocked ? 'مسدود' : (!$person->is_active ? 'غیرفعال' : (!$person->mobile ? 'بدون شماره تماس' : 'آماده ارسال')) }}</span></td>
            </tr>
        @empty
            <tr><td colspan="4" class="invitation-empty">مخاطبی ثبت نشده است.</td></tr>
        @endforelse
        </tbody>
    </table></div>
    <div class="invitation-pagination">{{ $recipients->links() }}</div>
    @if($canCreate || $canUpdate)
    <footer class="invitation-sendbar">
        <div class="invitation-expiry"><label for="invitation-hours">اعتبار دعوت (ساعت)</label><input id="invitation-hours" type="number" class="form-control" min="1" max="720" value="72" required></div>
        @if($canCreate)<span class="invitation-selection" data-selected-count aria-live="polite">۰ انتخاب‌شده</span><button type="button" class="btn btn-primary" data-send-selected disabled>ارسال دعوت‌نامه</button>@endif
    </footer>
    @endif
</section>
<div class="invitation-feedback" role="status" aria-live="polite" aria-atomic="true" data-send-result hidden></div>
<section class="invitation-panel" id="invitation-history" aria-labelledby="invitation-history-title">
    <header class="invitation-heading"><div><h2 id="invitation-history-title">سوابق ارسال</h2><span class="invitation-count">{{ $digits($invitations->total()) }} دعوت‌نامه</span></div><a href="{{ route('management.operations.show', ['resource' => 'invitations', 'building_id' => $building->id, 'recipients_page' => $recipients->currentPage(), 'history_page' => $invitations->currentPage()]) }}" class="btn btn-outline-primary" data-refresh-invitations>به‌روزرسانی</a></header>
    <div class="invitation-table-wrap"><table class="invitation-table">
        <caption class="visually-hidden">سوابق دعوت کاربران ساختمان {{ $building->title }}</caption>
        <thead><tr><th scope="col">مخاطب</th><th scope="col">واحد</th><th scope="col">وضعیت دعوت</th><th scope="col">زمان‌ها</th><th scope="col">عملیات</th></tr></thead>
        <tbody>
        @forelse($invitations as $invitation)
            @php
                $used = $invitation->accepted_at !== null || $invitation->status->value === 'accepted';
                [$state, $label] = $used ? ['success', 'استفاده‌شده'] : ($invitation->status->value === 'cancelled' ? ['muted', 'لغوشده'] : ($invitation->isExpired() ? ['muted', 'منقضی‌شده'] : ($invitation->status->value === 'sent' ? ['ready', 'ارسال‌شده'] : ['warning', 'ارسال تأیید نشده'])));
            @endphp
            <tr>
                <td data-label="مخاطب"><div class="invitation-person"><strong>{{ trim(($invitation->invitedUser?->first_name ?? '').' '.($invitation->invitedUser?->last_name ?? '')) ?: 'بدون نام' }}</strong><bdi dir="ltr">{{ $digits($invitation->mobile ?: '—') }}</bdi></div></td>
                <td data-label="واحد"><span class="invitation-unit">{{ $digits($invitation->unit?->unit_number ?? '—') }}</span></td>
                <td data-label="وضعیت دعوت"><span class="invitation-state" data-state="{{ $state }}">{{ $label }}</span></td>
                <td data-label="زمان‌ها"><dl class="invitation-dates">
                    @foreach(['ارسال' => $invitation->sent_at, 'انقضا' => $invitation->expires_at, 'استفاده' => $invitation->accepted_at] as $title => $date)
                    @if($date)<div><dt>{{ $title }}</dt><dd><time datetime="{{ $date->toIso8601String() }}" dir="ltr" data-invitation-date>{{ $digits($date->format('Y/m/d H:i')) }}</time></dd></div>@endif
                    @endforeach
                </dl></td>
                <td data-label="عملیات" class="invitation-row-actions">
                    @if($canUpdate && !$used && $invitation->status->value !== 'cancelled' && $invitation->invited_user_id)<button type="button" class="btn btn-outline-primary" data-resend="{{ $invitation->id }}" data-unit="{{ $invitation->unit_id }}" data-user="{{ $invitation->invited_user_id }}" aria-label="ارسال مجدد دعوت {{ $invitation->invitedUser?->first_name }} {{ $invitation->invitedUser?->last_name }} واحد {{ $digits($invitation->unit?->unit_number) }}">ارسال مجدد</button>@else<span class="invitation-placeholder">—</span>@endif
                    <span class="invitation-row-result" data-row-result></span>
                </td>
            </tr>
        @empty
            <tr><td colspan="5" class="invitation-empty">هنوز دعوت‌نامه‌ای ارسال نشده است.</td></tr>
        @endforelse
        </tbody>
    </table></div>
    <div class="invitation-pagination">{{ $invitations->links() }}</div>
</section>
@else
<div class="invitation-panel invitation-empty">ساختمان را انتخاب کنید.</div>
@endif
</div>
@endsection
@push('scripts')
<script src="{{ asset('js/buildino-building-invitations.js') }}?v={{ filemtime(public_path('js/buildino-building-invitations.js')) }}" defer></script>
@endpush
