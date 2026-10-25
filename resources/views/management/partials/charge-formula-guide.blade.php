<section class="charge-guide" aria-labelledby="charge-guide-title">
    <h4 id="charge-guide-title">از انتخاب هزینه تا فرمول آماده محاسبه</h4>
    <p>در چهار مرحله، دسته هزینه، روش تقسیم و تنظیمات را مشخص و نتیجه را بررسی کنید. فعال‌سازی فرمول، صورتحساب صادر نمی‌کند.</p>
    <details><summary>روش‌های قابل استفاده و ارتباط با دوره</summary><p>روش‌های فعلی: ثابت، متراژ، نفرات، تقسیم مساوی و ضریب طبقه. روش مصرفی، روش ترکیبی و تنظیم استثناها هنوز پشتیبانی نمی‌شوند.</p><p>در ساختار فعلی اتصال جداگانه به دوره وجود ندارد؛ دوره هنگام محاسبه، فرمول‌های فعال ساختمان را می‌خواند.</p></details>
    @if (in_array('charge-periods', $managementUi['visible_resources'] ?? [], true))
        <a class="crud-button crud-button--soft" href="{{ route('management.operations.show', 'charge-periods') }}" data-charge-period-link>مشاهده دوره‌های شارژ</a>
    @endif
</section>
