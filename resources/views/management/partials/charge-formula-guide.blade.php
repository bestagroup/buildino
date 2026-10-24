<section class="crud-context-card charge-guide" aria-labelledby="charge-guide-title">
    <div class="crud-context-card__heading"><strong id="charge-guide-title">تعریف فرمول شارژ · P12</strong><span>برای هر دسته هزینه مشخص کنید سهم واحدها چگونه محاسبه شود.</span></div>
    <p>ابتدا ساختمان را انتخاب کنید. با «تعریف فرمول شارژ» هزینه، روش تقسیم و تنظیمات آن را در چهار مرحله مشخص می‌کنید.</p>
    <details><summary>روش‌های قابل استفاده و ارتباط با دوره</summary><p>روش‌های فعلی: ثابت، متراژ، نفرات، تقسیم مساوی و ضریب طبقه. روش مصرفی، روش ترکیبی و تنظیم استثناها هنوز پشتیبانی نمی‌شوند.</p><p>در ساختار فعلی اتصال جداگانه به دوره وجود ندارد؛ دوره هنگام محاسبه، فرمول‌های فعال ساختمان را می‌خواند.</p></details>
    @if (in_array('charge-periods', $managementUi['visible_resources'] ?? [], true))
        <a class="crud-button crud-button--soft" href="{{ route('management.operations.show', 'charge-periods') }}" data-charge-period-link>مشاهده دوره‌های شارژ</a>
    @endif
</section>
