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
    <p>فایل با ۱۵ ستون و اطلاعات فعلی واحدها، مالک و ساکن غیرمالک آماده می‌شود. نام ساختمان، کد ساختمان، شماره طبقه و شماره واحد را تغییر ندهید. هر واحد یک ردیف دارد؛ برای هر ساختمان فایل جداگانه بارگذاری کنید.</p>
    <p>مشخصات موجود مالک و ساکن را اصلاح یا اطلاعات افراد جدید را تکمیل کنید. اگر مستاجر خالی و تعداد ساکن بیشتر از صفر باشد، مالک ساکن محسوب می‌شود؛ تعداد صفر در محاسبات نفرمحور سهمی ندارد؛ پایان سکونت از فرم سکونت ثبت می‌شود. شروع ارتباط‌های جدید و تغییر تعداد نفرات از تاریخ ثبت فایل است. متراژ و تعداد اتاق و تعداد اعلام‌شده پارکینگ قابل تکمیل‌اند؛ تعداد پارکینگ تخصیص جای پارک مشخص نیست.</p>
    <p>نام، نام خانوادگی، موبایل، متراژ و تعدادها قابل اصلاح‌اند. موبایل با قالب متنی 09123456789 ذخیره می‌شود؛ اعداد فارسی و پیش‌شماره ایران نیز هنگام ورود یکسان‌سازی می‌شوند. تغییر مشخصات حساب مشترک در تمام واحدهای همان کاربر دیده می‌شود و نیازمند مجوز ویرایش کاربر است. با تغییر شماره، تأیید قبلی موبایل لغو می‌شود.</p>
    <p>ستون‌های خاکستری، مشخصات شناسایی ردیف هستند و نباید تغییر کنند. برگه داخلی فایل را حذف نکنید. اگر اطلاعات سامانه پس از دانلود تغییر کند، فایل جدید دریافت کنید. حذف مالک یا ساکن و انتقال ارتباط به فرد دیگر از فرم مالکیت یا سکونت انجام می‌شود. حداکثر ۵۰۰ ردیف و فایل XLSX تا ۲ مگابایت مجاز است. پیامک خودکار ارسال نمی‌شود.</p>
    <p>فونت فایل B Nazanin است؛ نمایش دقیق آن به نصب این فونت در برنامه اکسل بستگی دارد.</p>
    <a class="btn btn-outline-primary" href="{{ route('management.user-import.template', $building) }}">دانلود فایل نمونه و اطلاعات فعلی ساختمان</a>
    @isset($rows)
        <div class="table-responsive mt-3"><table class="table"><thead><tr>
        @foreach(\App\Services\Web\BuildingWorkbookService::HEADERS as $header)<th>{{ $header }}</th>@endforeach
        </tr></thead><tbody>
        @foreach($rows as $row)<tr>@foreach(\App\Services\Web\BuildingWorkbookService::KEYS as $key)<td>{{ $row[$key] }}</td>@endforeach</tr>@endforeach
        </tbody></table></div>
        @if(count($rows))
        <form method="post" action="{{ route('management.user-import.commit', $building) }}">@csrf<input type="hidden" name="token" value="{{ $token }}"><button class="btn btn-primary">تأیید و ثبت {{ count($rows) }} ردیف</button></form>
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
