<!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>تغییر رمز اولیه | بیلدینو</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('images/buildino-mark.svg') }}">
    <link rel="stylesheet" href="{{ asset('css/buildino-fonts.css') }}">
    <style>
        *{box-sizing:border-box}
        body{min-height:100vh;margin:0;display:grid;place-items:center;background:#f4f7fb;color:#192b3e;font-family:var(--buildino-font-persian,Tahoma,sans-serif);padding:20px}
        main{width:min(100%,470px);padding:28px;border:1px solid #dce5ef;border-radius:20px;background:#fff;box-shadow:0 14px 52px rgba(8,35,61,.08)}
        img{display:block;max-width:100%;width:150px;height:100px;object-fit:contain;margin:0 auto 14px}
        h1{font-size:22px;line-height:1.6;margin:0 0 8px}
        p{color:#51657a;line-height:1.9;font-size:13px}
        label{display:block;font-size:13px;font-weight:700;margin:14px 0}
        input{display:block;width:100%;height:44px;border:1px solid #cbd7e4;border-radius:10px;padding:10px 12px;margin-top:6px;direction:ltr;font-size:15px}
        input:focus-visible{outline:3px solid #8edbd3}
        button{width:100%;min-height:46px;border:0;border-radius:10px;background:#116d76;color:white;font:inherit;font-weight:700;cursor:pointer}
        .errors{border-radius:10px;background:#fff1f1;color:#ae3434;padding:12px;font-size:12px;line-height:1.8}
    </style>
</head>
<body>
<main>
    <img src="{{ asset('images/buildino-logo-horizontal.svg') }}" alt="بیلدینو Buildino">
    <h1>تعیین رمز عبور شخصی</h1>
    <p>این حساب توسط مدیر ساختمان ایجاد شده است. برای امنیت اطلاعات، پیش از دسترسی به سامانه رمز اولیه را تغییر دهید.</p>
    @if ($errors->any())
        <div class="errors" role="alert">
            @foreach ($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif
    <form method="POST" action="{{ route('password.first-change.update') }}">
        @csrf
        <label for="current_password">رمز عبور اولیه
            <input id="current_password" name="current_password" type="password" autocomplete="current-password" required>
        </label>
        <label for="password">رمز عبور جدید (حداقل ۱۰ کاراکتر شامل حروف و اعداد)
            <input id="password" name="password" type="password" autocomplete="new-password" minlength="10" required>
        </label>
        <label for="password_confirmation">تکرار رمز عبور جدید
            <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" minlength="10" required>
        </label>
        <button type="submit">ثبت رمز جدید و ورود به سامانه</button>
    </form>
    <p>پس از ثبت موفق، می‌توانید با شماره موبایل یا ایمیل و رمز جدید خود وارد شوید.</p>
</main>
</body>
</html>
