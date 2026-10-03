<!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} | Buildino</title>
    <link rel="stylesheet" href="{{ asset('css/public-site.css') }}">
</head>
<body>
<header class="public-nav">
    <div class="container public-nav__inner">
        <a class="brand" href="{{ route('public.home') }}">
            <span class="brand__mark">B</span>
            <span>Buildino</span>
        </a>
        <div class="public-nav__actions">
            <a class="button button--ghost" href="{{ route('portal.login') }}">ورود کاربران</a>
            <a class="button button--primary" href="{{ route('login') }}">ورود مدیریت</a>
        </div>
    </div>
</header>

<main class="legal">
    <div class="container">
        <article class="legal__card">
            <span class="eyebrow">اسناد استفاده از سامانه</span>
            <h1>{{ $title }}</h1>

            @foreach ($sections as $section)
                <section>
                    <h2>{{ $section['title'] }}</h2>
                    <p>{{ $section['body'] }}</p>
                </section>
            @endforeach

            <p>
                نسخه نهایی حقوقی این متن باید پیش از انتشار عمومی توسط مالک سامانه و
                مشاور حقوقی تأیید شود.
            </p>
        </article>
    </div>
</main>
</body>
</html>
