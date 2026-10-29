<header class="ownership-hero"><div class="ownership-hero__icon">@include('management.partials.icon', ['name' => 'wallet', 'size' => 27])</div><div><span class="eyebrow">تعریف فرمول شارژ · P12</span><h2>سهم هر واحد، با قاعده‌ای روشن</h2><p>برای هر دسته هزینه، روش تقسیم بین واحدهای ساختمان را مشخص کنید.</p></div></header>

@include('management.partials.finance-navigation', ['currentResource' => 'charge-formulas'])
<section class="ownership-card" data-charge-location aria-labelledby="charge-location-title">
    <div class="ownership-card__heading"><div><h3 id="charge-location-title" tabindex="-1">فرمول‌های کدام ساختمان؟</h3><p>فقط ساختمان‌های قابل دسترسی شما نمایش داده می‌شوند.</p></div></div>
    <div id="crudContextFields"><label class="ownership-field"><span>ساختمان</span><select data-context-name="building_id" data-required="1" data-select2="off"><option value="">انتخاب ساختمان</option>@foreach ($formulaBuildings as $building)<option value="{{ $building['id'] }}" @selected($formulaBuildings->count() === 1)>{{ $building['title'] }}</option>@endforeach</select></label></div>
    @if ($formulaBuildings->isEmpty())<p class="ownership-empty">ساختمان قابل دسترسی برای مشاهده فرمول‌های شارژ وجود ندارد.</p>@endif
    <div class="ownership-actions"><button type="button" class="ownership-button ownership-button--primary" data-charge-resume hidden>نمایش فرمول‌های ساختمان</button></div>
</section>

<section class="ownership-card" data-charge-directory hidden aria-labelledby="charge-directory-title">
    <div class="ownership-card__heading ownership-card__heading--split"><div><h3 id="charge-directory-title" tabindex="-1">فرمول‌های شارژ ساختمان</h3><p data-charge-building-label></p></div><div class="charge-page-toolbar"><button type="button" class="ownership-button" data-charge-change-building>تغییر ساختمان</button><button type="button" class="ownership-button" id="crudRefreshButton">به‌روزرسانی</button><button type="button" class="ownership-button ownership-button--primary" id="crudCreateButton" disabled hidden>+ تعریف فرمول شارژ</button></div></div>
    @include('management.partials.charge-formula-guide')
    <label class="ownership-field charge-page-search"><span>جست‌وجو در فرمول‌های همین صفحه</span><input type="search" id="crudSearch" placeholder="عنوان فرمول"></label>
    <div class="crud-state" id="crudState" role="status" aria-live="polite"><span>در حال دریافت فرمول‌ها…</span></div>
    <div class="crud-table-wrap charge-page-table" id="crudTableWrap" hidden role="region" aria-label="فهرست فرمول‌های شارژ" tabindex="0">
        <table class="crud-table"><caption class="charge-sr-only">عنوان، روش تقسیم، وضعیت و عملیات فرمول‌های شارژ ساختمان انتخاب‌شده</caption><thead><tr id="crudTableHead"></tr></thead><tbody id="crudTableBody"></tbody></table>
    </div>
</section>
