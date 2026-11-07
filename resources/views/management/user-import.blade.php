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
    <p>فایل نمونه با ۱۵ ستون و اطلاعات طبقات و واحدهای همین ساختمان آماده می‌شود. نام ساختمان، کد ساختمان، شماره طبقه و شماره واحد را تغییر ندهید. هر واحد یک ردیف دارد؛ برای هر ساختمان فایل جداگانه بارگذاری کنید.</p>
    <p>مشخصات مالک الزامی و مشخصات مستاجر اختیاری است. اگر مستاجر خالی و تعداد ساکن بیشتر از صفر باشد، مالک ساکن محسوب می‌شود؛ تعداد صفر یعنی واحد خالی. شروع ارتباط‌های جدید و تغییر تعداد نفرات از تاریخ ثبت فایل است. متراژ و تعداد اتاق و تعداد اعلام‌شده پارکینگ قابل تکمیل‌اند؛ تعداد پارکینگ تخصیص جای پارک مشخص نیست.</p>
    <p>موبایل را به صورت متن وارد کنید. حساب موجود باید با نام واردشده تطبیق داشته باشد. ارتباط فعال ناسازگار جایگزین نمی‌شود؛ ابتدا آن را در فرم مالکیت یا سکونت اصلاح کنید. حداکثر ۵۰۰ ردیف، XLSX تا ۲ مگابایت؛ فقط برگه اول خوانده می‌شود. ردیف‌های بدون مشخصات مالک و مستاجر نادیده گرفته می‌شوند. پیامک خودکار ارسال نمی‌شود.</p>
    <a class="btn btn-outline-primary" href="{{ route('management.user-import.template', $building) }}">دانلود فایل نمونه واحدهای ساختمان</a>
    @isset($rows)
        <div class="table-responsive mt-3"><table class="table"><thead><tr>
        @foreach(\App\Services\Web\BuildingWorkbookService::HEADERS as $header)<th>{{ $header }}</th>@endforeach
        </tr></thead><tbody>
        @foreach($rows as $row)<tr>@foreach(\App\Services\Web\BuildingWorkbookService::KEYS as $key)<td>{{ $row[$key] }}</td>@endforeach</tr>@endforeach
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
