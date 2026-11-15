@extends('management.layouts.app')
@section('title', 'ورود کاربران از اکسل | Buildino')
@section('page-title', 'ورود کاربران از اکسل')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/buildino-user-import.css') }}?v={{ filemtime(public_path('css/buildino-user-import.css')) }}">
@endpush
@section('content')
<div class="card p-4">
    <h2>ورود کاربران و ارتباط با واحد</h2>
    @if(session('status')) <div class="alert alert-success" role="status">{{ session('status') }}</div> @endif
    @if($errors->any()) <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div> @endif
    @if($buildings->count() > 1)
    <form method="get" action="{{ route('management.user-import.index') }}">
        <label for="import-building">ساختمان</label>
        <select id="import-building" class="form-select" name="building_id" required>
            <option value="">انتخاب ساختمان</option>
            @foreach($buildings as $item)<option value="{{ $item->id }}" @selected($building?->id === $item->id)>{{ $item->title }}</option>@endforeach
        </select><div class="import-preview__actions"><button class="btn btn-primary mt-2">انتخاب</button></div>
    </form>
    @endif
    @if($building)
    <h3>{{ $building->title }}</h3>
    <a class="btn btn-outline-primary" href="{{ route('management.user-import.template', $building) }}">دانلود فایل نمونه و اطلاعات فعلی ساختمان</a>
    @isset($rows)
        @php
            $digits = static fn ($value) => strtr((string) $value, array_combine(range(0, 9), ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹']));
        @endphp
        <section class="import-preview" aria-labelledby="import-preview-heading">
            <header class="import-preview__header"><h3 id="import-preview-heading">پیش‌نمایش اطلاعات</h3><span class="import-preview__count">{{ $digits(count($rows)) }} ردیف</span></header>
            <div class="import-preview__scroll" tabindex="0" role="region" aria-label="پیش‌نمایش فایل اکسل">
                <table class="import-preview__table">
                    <thead><tr><th scope="col">ردیف</th><th scope="col">ساختمان</th><th scope="col">طبقه و واحد</th><th scope="col">مشخصات واحد</th><th scope="col">مالک</th><th scope="col">ساکن</th><th scope="col">تعداد ساکنان</th></tr></thead>
                    <tbody>
                    @forelse($rows as $row)
                        <tr>
                            <td>{{ $digits($row['row']) }}</td>
                            <td><strong>{{ $row['building'] }}</strong><small class="import-preview__secondary">{{ $row['code'] }}</small></td>
                            <td><strong>واحد {{ $digits($row['unit']) }}</strong><small class="import-preview__secondary">طبقه {{ $digits($row['floor']) }}</small></td>
                            <td><div class="import-preview__specs"><span>{{ $digits($row['area']) }} متر</span><span>{{ $digits($row['bedrooms']) }} اتاق</span><span>{{ $digits($row['parking_count']) }} پارکینگ</span><span>{{ $digits($row['storage_count']) }} انباری</span></div><small class="import-preview__secondary">{{ \App\Services\Web\BuildingWorkbookService::USAGE_LABELS[$row['usage_type']] ?? $row['usage_type'] }}</small></td>
                            @foreach(['owner', 'tenant'] as $person)
                            <td><strong>{{ trim(($row[$person.'_first'] ?? '').' '.($row[$person.'_last'] ?? '')) ?: '—' }}</strong>@if($row[$person.'_mobile'] ?? '')<bdi class="import-preview__secondary import-preview__phone" dir="ltr">{{ $digits($row[$person.'_mobile']) }}</bdi>@endif</td>
                            @endforeach
                            <td><span class="import-preview__count">{{ $digits($row['resident_count']) }} نفر</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="import-preview__empty">تغییری برای نمایش وجود ندارد.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </section>
        @if(count($rows))
        <form class="import-preview__actions" method="post" action="{{ route('management.user-import.commit', $building) }}">@csrf<input type="hidden" name="token" value="{{ $token }}"><button class="btn btn-primary">تأیید و ثبت {{ $digits(count($rows)) }} ردیف</button></form>
        @else
        <p class="alert alert-info">تغییری برای ثبت وجود ندارد.</p>
        @endif
    @else
        <form class="mt-3" method="post" enctype="multipart/form-data" action="{{ route('management.user-import.preview', $building) }}">@csrf
            <label for="users-file">فایل اکسل کاربران</label><input class="form-control" id="users-file" type="file" name="file" accept=".xlsx" required>
            <button class="btn btn-primary mt-2">بررسی و پیش‌نمایش</button>
        </form>
    @endisset
    @endif
</div>
@endsection
