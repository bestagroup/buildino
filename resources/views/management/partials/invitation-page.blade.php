<header class="ownership-hero">
    <div class="ownership-hero__icon">@include('management.partials.icon', ['name' => 'mail', 'size' => 27])</div>
    <div><h2>مدیریت دعوت‌نامه‌ها</h2><p>ارسال دعوت‌نامه و پیگیری وضعیت دعوت‌های واحد.</p></div>
</header>

<section class="ownership-card" data-invitation-location aria-labelledby="invitation-location-title">
    <div class="ownership-card__heading"><div><h3 id="invitation-location-title" tabindex="-1">انتخاب واحد</h3><p>واحد را انتخاب کنید تا دعوت‌نامه‌های آن نمایش داده شود.</p></div></div>
    <div id="crudContextFields">
        <label class="ownership-field"><span>واحد <b aria-hidden="true">*</b></span><select data-context-name="unit_id" data-lookup="units" data-required="1" required aria-describedby="invitation-unit-help"><option value="">انتخاب واحد</option></select></label>
        <p class="invitation-hint" id="invitation-unit-help">فقط واحدهای محدوده دسترسی شما قابل انتخاب هستند.</p>
    </div>
    <div class="ownership-actions"><button type="button" class="ownership-button ownership-button--primary" data-invitation-resume hidden>نمایش دعوت‌نامه‌ها</button></div>
</section>

<section class="ownership-card" data-invitation-directory hidden aria-labelledby="invitation-directory-title">
    <div class="ownership-card__heading ownership-card__heading--split">
        <div><h3 id="invitation-directory-title" tabindex="-1">دعوت‌نامه‌های واحد</h3><p data-invitation-unit-label></p></div>
        <div class="ownership-actions"><button type="button" class="ownership-button" data-invitation-change-unit>تغییر واحد</button><button type="button" class="ownership-button" id="crudRefreshButton">به‌روزرسانی</button>@if (!empty($resource['create']))<button type="button" class="ownership-button ownership-button--primary" id="crudCreateButton" disabled>+ دعوت جدید</button>@endif</div>
    </div>
    <p class="invitation-note">دعوت پیامکی برای کاربران ثبت‌شده و دارای رابطه فعال با این واحد ارسال می‌شود. اطلاعات تماس و نوع ارتباط از اطلاعات موجود خوانده می‌شوند.</p>
    <label class="ownership-field invitation-search"><span>جست‌وجو در دعوت‌های همین صفحه</span><input type="search" id="crudSearch" placeholder="نام یا شماره موبایل"></label>
    <div class="crud-state" id="crudState" role="status" aria-live="polite"><span>در حال دریافت دعوت‌نامه‌ها…</span></div>
    <div class="crud-table-wrap" id="crudTableWrap" hidden role="region" aria-label="فهرست دعوت‌نامه‌های واحد" tabindex="0">
        <table class="crud-table"><caption class="invitation-sr-only">دریافت‌کننده، نوع ارتباط، اطلاعات تماس، وضعیت و انقضای دعوت‌نامه‌ها</caption><thead><tr id="crudTableHead"></tr></thead><tbody id="crudTableBody"></tbody></table>
    </div>
</section>
