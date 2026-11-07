@extends('management.layouts.app')
@section('title', 'ورود کاربران از اکسل | Buildino')
@section('page-title', 'ورود کاربران از اکسل')
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
        </select><button class="btn btn-primary mt-2">انتخاب</button>
    </form>
    @endif
    @if($building)
    <h3>{{ $building->title }}</h3>
    <p>ابتدا واحدها را ایجاد کنید و فایل نمونه همین ساختمان را دانلود کنید. شناسه و شماره واحد را تغییر ندهید. برای چند مالک یا ساکن، ردیف واحد را کپی کنید.</p>
    <p>ستون‌ها: شناسه واحد، شماره واحد، نام، نام خانوادگی، موبایل، نوع رابطه، تعداد کل نفرات واحد و تاریخ شروع میلادی (YYYY-MM-DD). موبایل را به صورت متن وارد کنید. نوع رابطه: owner (مالک)، resident (ساکن)، tenant (مستاجر)، owner_resident (مالک ساکن). حداکثر ۵۰۰ ردیف، فایل XLSX تا ۲ مگابایت؛ فقط برگه اول خوانده می‌شود.</p>
    <p>شماره موبایل شناسه کاربر است؛ نام حساب موجود باید مطابقت داشته باشد. حساب موجود، ارتباط فعال قبلی و کیف پول‌ها بازنویسی نمی‌شوند. برای هر واحد در یک تاریخ، تعداد کل نفرات یکسان درج شود. پیامک خودکار ارسال نمی‌شود.</p>
    <a class="btn btn-outline-primary" href="{{ route('management.user-import.template', $building) }}">دانلود فایل نمونه واحدهای ساختمان</a>
    @isset($rows)
        <div class="table-responsive mt-3"><table class="table"><thead><tr><th>واحد</th><th>نام</th><th>موبایل</th><th>رابطه</th><th>نفرات</th><th>شروع</th></tr></thead><tbody>
        @foreach($rows as $row)<tr><td>{{ $row['unit_number'] }}</td><td>{{ $row['first_name'] }} {{ $row['last_name'] }}</td><td dir="ltr">{{ $row['mobile'] }}</td><td>{{ $row['relation'] }}</td><td>{{ $row['resident_count'] }}</td><td dir="ltr">{{ $row['starts_at'] }}</td></tr>@endforeach
        </tbody></table></div>
        <form method="post" action="{{ route('management.user-import.commit', $building) }}">@csrf<input type="hidden" name="token" value="{{ $token }}"><button class="btn btn-primary">تأیید و ثبت {{ count($rows) }} ردیف</button></form>
    @else
        <form class="mt-3" method="post" enctype="multipart/form-data" action="{{ route('management.user-import.preview', $building) }}">@csrf
            <label for="users-file">فایل اکسل کاربران</label><input class="form-control" id="users-file" type="file" name="file" accept=".xlsx" required>
            <button class="btn btn-primary mt-2">بررسی و پیش‌نمایش</button>
        </form>
    @endisset
    @endif
</div>
@endsection
