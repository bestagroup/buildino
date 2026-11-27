@php
    $statuses = match($resource) {
        'services' => ['open'=>'باز','assigned'=>'تخصیص‌یافته','in_progress'=>'در حال انجام','awaiting_confirmation'=>'در انتظار تأیید مشتری','completed'=>'تکمیل‌شده','cancelled'=>'لغوشده'],
        'payouts' => ['pending'=>'در انتظار بررسی','approved'=>'تأییدشده','paid'=>'پرداخت‌شده','rejected'=>'ردشده','cancelled'=>'لغوشده'],
        default => [],
    };
@endphp
<div class="provider-workspace" data-provider-workspace>
    <nav class="portal-breadcrumb" aria-label="مسیر صفحه"><a href="{{ route('portal.provider.dashboard') }}">پنل خدمات</a><span aria-hidden="true">/</span><strong>{{ $operationConfig['title'] }}</strong></nav>
    <header class="provider-heading"><h2>{{ $operationConfig['title'] }}</h2></header>
    <section class="provider-panel" data-dt-filter-scope>
        <div class="portal-datatable-toolbar provider-filters">
            @if($statuses)<label class="portal-field"><span>وضعیت</span><select data-dt-filter="status"><option value="">همه وضعیت‌ها</option>@foreach($statuses as $key=>$label)<option value="{{ $key }}" @selected(request('status') === $key)>{{ $label }}</option>@endforeach</select></label>@endif
            <label class="portal-field"><span>از تاریخ</span><input type="date" data-dt-filter="from" value="{{ request('from') }}"></label>
            <label class="portal-field"><span>تا تاریخ</span><input type="date" data-dt-filter="to" value="{{ request('to') }}"></label>
            <button type="button" class="portal-action-button" data-dt-reset>پاک‌کردن فیلترها</button>
        </div>
        <div class="portal-datatable-card">@include('shared.server-datatable',['id'=>'provider-'.$resource.'-table','url'=>$dataUrl,'columns'=>$operationConfig['columns'] ?? [],'pageLength'=>15,'persianDigits'=>true])</div>
    </section>
</div>
