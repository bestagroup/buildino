@php
    $create = ['guests'=>['guestModal','ثبت مهمان'], 'reservations'=>['reservationModal','رزرو جدید'], 'services'=>['serviceModal','درخواست خدمت'], 'support'=>['supportModal','تیکت جدید']][$resource] ?? null;
    $selectedUnit = request()->integer('unit_id') ?: ($resource !== 'wallet' && count($portalData['units']) === 1 ? $portalData['units'][0]['id'] : null);
@endphp
<div data-resident-workspace data-resident-resource="{{ $resource }}">
    <nav class="portal-breadcrumb" aria-label="مسیر صفحه"><a href="{{ route('portal.resident.dashboard') }}">خانه من</a><span>/</span><strong>{{ $operationConfig['title'] }}</strong></nav>
    <header class="resident-page-heading"><div><h2>{{ $operationConfig['title'] }}</h2></div>@if($create)<button type="button" class="portal-primary-button" data-resident-create data-bs-toggle="modal" data-bs-target="#{{ $create[0] }}" @disabled(empty($portalData['units']))>+ {{ $create[1] }}</button>@endif</header>
    @if(request()->boolean('created'))<div class="resident-notice" role="status">اطلاعات ثبت شد.</div>@endif
    @if(empty($portalData['units']) && $resource !== 'wallet')
        <section class="resident-panel resident-empty"><h3>واحدی متصل نشده است</h3><p>کد دعوت را از مدیر ساختمان دریافت کنید.</p><button class="portal-primary-button" type="button" data-invitation-open>واردکردن کد دعوت</button></section>
    @else
    @if($resource === 'reservations' && $portalData['facilities']->isEmpty())<p class="resident-notice" role="status">هنوز امکان فعالی برای رزرو تعریف نشده است.</p>@endif
    <section class="resident-panel resident-records" data-dt-filter-scope>
        <div class="portal-datatable-toolbar resident-filters">
            <label class="portal-field"><span>واحد</span><select data-dt-filter="unit_id" aria-label="فیلتر واحد"><option value="">{{ $resource === 'wallet' ? 'همه کیف پول‌ها، شامل کیف پول شخصی' : 'همه واحدها' }}</option>@foreach($portalData['units'] as $unit)<option value="{{ $unit['id'] }}" @selected($selectedUnit === (int) $unit['id'])>{{ $unit['building_title'] }} / {{ $unit['title'] }}</option>@endforeach</select></label>
            @if($residentStatuses)<label class="portal-field"><span>وضعیت</span><select data-dt-filter="status"><option value="">همه وضعیت‌ها</option>@foreach($residentStatuses as $state=>$label)<option value="{{ $state }}" @selected(request('status') === $state)>{{ $label }}</option>@endforeach</select></label>@endif
            <label class="portal-field"><span>از تاریخ</span><input type="date" data-dt-filter="from" value="{{ request('from') }}"></label>
            <label class="portal-field"><span>تا تاریخ</span><input type="date" data-dt-filter="to" value="{{ request('to') }}"></label>
            <button type="button" class="portal-action-button" data-dt-reset>پاک‌کردن فیلترها</button>
        </div>
        <div class="portal-datatable-card">@include('shared.server-datatable', ['id'=>'resident-'.$resource.'-table','url'=>$dataUrl,'columns'=>$operationConfig['columns'] ?? [],'pageLength'=>15,'persianDigits'=>true])</div>
    </section>
    @endif
</div>
@include('portal.resident.forms')
