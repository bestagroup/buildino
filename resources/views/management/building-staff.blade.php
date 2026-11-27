@extends('management.layouts.app')
@section('title', 'کارکنان ساختمان | Buildino')
@section('page-title', 'کارکنان ساختمان')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/buildino-unit-ownerships.css') }}?v={{ filemtime(public_path('css/buildino-unit-ownerships.css')) }}">
<link rel="stylesheet" href="{{ asset('css/buildino-building-staff.css') }}?v={{ filemtime(public_path('css/buildino-building-staff.css')) }}">
@endpush
@section('content')
@php
    $digits = ['0'=>'۰','1'=>'۱','2'=>'۲','3'=>'۳','4'=>'۴','5'=>'۵','6'=>'۶','7'=>'۷','8'=>'۸','9'=>'۹',','=>'٬'];
    $number = fn ($value) => strtr(number_format((int) $value), $digits);
    $types = config('building_staff.types');
    $selectedType = old('employee_type', $editing?->employee_type ?? 'concierge');
    if (!is_string($selectedType) || !isset($types[$selectedType])) $selectedType = 'concierge';
    $selectedServices = (array) old('services', session()->hasOldInput() ? [] : ($editing?->services ?? []));
    $selectedPermissions = (array) old('permissions', session()->hasOldInput() ? [] : ($editing?->assignment->role->permissions->pluck('name')->all() ?? []));
    $editorOpen = $building && ($editing || request()->boolean('create') || $errors->any());
    $directoryUrl = route('management.operations.show', array_filter(['resource'=>'building-staff','building_id'=>$building?->id]));
    $permissionGroups = [
        'مهمان و تردد'=>['guests','guest-visits'], 'امکانات و رزرو'=>['facilities','facility-reservations'],
        'درخواست خدمات'=>['service-requests'], 'پشتیبانی'=>['support-tickets'],
    ];
@endphp
<div class="ownership-page staff-page" data-staff-page>
    <nav class="staff-breadcrumb" aria-label="مسیر صفحه"><a href="{{ route('management.operations.index') }}">مرکز عملیات</a><span aria-hidden="true">/</span><strong>کارکنان ساختمان</strong></nav>
    <header class="staff-heading"><div class="staff-heading__identity"><span class="staff-heading__icon" aria-hidden="true">@include('management.partials.icon',['name'=>'users','size'=>26])</span><div><h2>کارکنان ساختمان</h2><p>{{ $building?->title ?? 'مدیریت کارکنان و دسترسی‌های ساختمان' }}</p></div></div>@if($building && !$editorOpen)<a class="ownership-button ownership-button--primary" href="{{ $directoryUrl.'&create=1' }}">@include('management.partials.icon',['name'=>'plus','size'=>18]) افزودن کارمند</a>@endif</header>
    @if(session('status'))<p class="ownership-alert ownership-alert--success" role="status">{{ session('status') }}</p>@endif
    @if($buildings->count() > 1 || !$building)
    <section class="ownership-card staff-location"><form method="GET"><label class="ownership-field" for="staff-building"><span>ساختمان</span><select id="staff-building" name="building_id" required @disabled($buildings->isEmpty())><option value="">انتخاب ساختمان</option>@foreach($buildings as $item)<option value="{{ $item->id }}" @selected($building?->id === $item->id)>{{ $item->title }}</option>@endforeach</select></label><button class="ownership-button" type="submit" @disabled($buildings->isEmpty())>نمایش کارکنان</button></form></section>
    @endif
    @if(!$building)
        <section class="ownership-card staff-empty">@include('management.partials.icon',['name'=>'building','size'=>34])<h3>{{ $buildings->isEmpty() ? 'ساختمانی در دسترس نیست' : 'ساختمان را انتخاب کنید' }}</h3><p>{{ $buildings->isEmpty() ? 'برای مدیریت کارکنان، دسترسی ساختمان لازم است.' : 'پس از انتخاب ساختمان، کارکنان و فرم ثبت در دسترس هستند.' }}</p></section>
    @elseif($editorOpen)
        <section class="ownership-card staff-editor" aria-labelledby="staff-editor-title">
            <header class="staff-section-heading"><div><h3 id="staff-editor-title">{{ $editing ? 'ویرایش کارمند' : 'افزودن کارمند' }}</h3><p>{{ $building->title }}</p></div><a class="ownership-button" href="{{ $directoryUrl }}" data-staff-cancel>بازگشت به فهرست</a></header>
            @if($errors->any())<div class="ownership-alert ownership-alert--error" role="alert" tabindex="-1" data-staff-error-summary><strong>اطلاعات زیر را بررسی کنید</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
            <form method="POST" action="{{ $editing ? route('management.building-staff.update',[$building,$editing]) : route('management.building-staff.store',$building) }}" data-staff-form>
                @csrf @if($editing) @method('PATCH') @endif
                <fieldset class="staff-form-section"><legend>مشخصات کارمند</legend>
                    @if($editing)<div class="staff-person"><span class="staff-avatar" aria-hidden="true">{{ mb_substr($editing->user->first_name ?: $editing->user->last_name,0,1) }}</span><div><strong>{{ $editing->user->first_name }} {{ $editing->user->last_name }}</strong><bdi>{{ strtr($editing->user->mobile, $digits) }}</bdi></div><span class="staff-muted">مشخصات حساب در این فرم تغییر نمی‌کند.</span></div>@else
                    <div class="staff-identity-grid">@foreach(['first_name'=>'نام','last_name'=>'نام خانوادگی','mobile'=>'شماره موبایل'] as $field=>$label)
                        <label class="ownership-field" for="staff-{{ $field }}"><span>{{ $label }} <b aria-hidden="true">*</b></span><input id="staff-{{ $field }}" name="{{ $field }}" type="{{ $field === 'mobile' ? 'tel' : 'text' }}" value="{{ old($field) }}" required maxlength="{{ $field === 'mobile' ? 11 : 100 }}" autocomplete="{{ ['first_name'=>'given-name','last_name'=>'family-name','mobile'=>'tel-national'][$field] }}" @if($field === 'mobile') inputmode="tel" dir="ltr" placeholder="09123456789" pattern="09[0-9]{9}" @endif @if($errors->has($field)) aria-invalid="true" aria-describedby="staff-{{ $field }}-error" @endif>@error($field)<small class="staff-field-error" id="staff-{{ $field }}-error">{{ $message }}</small>@enderror</label>
                    @endforeach</div>
                    <p class="staff-hint">ورود کارمند با شماره موبایل و کد یک‌بارمصرف است؛ حساب موجود با همین شماره متصل می‌شود.</p>@endif
                    <div class="staff-settings-grid"><label class="ownership-field" for="staff-type"><span>نوع کارمند <b aria-hidden="true">*</b></span><select name="employee_type" id="staff-type" required @if($errors->has('employee_type')) aria-invalid="true" aria-describedby="staff-type-error" @endif>@foreach($types as $key=>$type)<option value="{{ $key }}" @selected($selectedType === $key)>{{ $type['title'] }}</option>@endforeach</select>@error('employee_type')<small class="staff-field-error" id="staff-type-error">{{ $message }}</small>@enderror</label>
                        <div class="ownership-field"><span>وضعیت دسترسی کارمند</span><input type="hidden" name="is_active" value="0"><label class="buildino-switch"><input class="buildino-switch__input" type="checkbox" role="switch" name="is_active" value="1" @checked((int)old('is_active',$editing?->assignment->is_active ?? 1) === 1)><span class="buildino-switch__track" aria-hidden="true"></span><span class="buildino-switch__label">دسترسی به این ساختمان</span><span class="buildino-switch__state" aria-hidden="true"><span class="buildino-switch__on">فعال</span><span class="buildino-switch__off">غیرفعال</span></span></label>@error('is_active')<small class="staff-field-error">{{ $message }}</small>@enderror</div>
                    </div>
                </fieldset>
                <fieldset class="staff-form-section" aria-describedby="staff-services-help"><legend>خدمات کاری <b aria-hidden="true">*</b></legend><p id="staff-services-help" class="staff-hint">حداقل یک خدمت را انتخاب کنید.</p>
                    @foreach($types as $key=>$type)<div class="staff-choices" data-staff-services="{{ $key }}" @if($selectedType !== $key) hidden @endif>@foreach($type['services'] as $value=>$label)<label class="staff-choice"><input type="checkbox" name="services[]" value="{{ $value }}" @disabled($selectedType !== $key) @checked($selectedType === $key && in_array($value,$selectedServices,true))><span>{{ $label }}</span></label>@endforeach</div>@endforeach
                    <p class="staff-field-error" id="staff-services-error" data-staff-services-error role="alert" @if(!$errors->has('services') && !$errors->has('services.*')) hidden @endif>{{ $errors->first('services') ?: $errors->first('services.*') ?: 'حداقل یک خدمت را انتخاب کنید.' }}</p>
                </fieldset>
                <fieldset class="staff-form-section" aria-describedby="staff-permissions-help"><legend>دسترسی‌های سامانه <span class="staff-count" data-staff-permission-count>{{ $number(count(array_intersect($selectedPermissions,array_keys($options)))) }} انتخاب</span></legend><p class="staff-hint" id="staff-permissions-help">اختیاری؛ فقط مجوزهای لازم در همین ساختمان را انتخاب کنید. انتخاب خدمات کاری، دسترسی سامانه ایجاد نمی‌کند.</p>
                    <div class="staff-permission-groups">@foreach($permissionGroups as $groupTitle=>$prefixes)
                        @php($groupOptions = array_filter($options, fn ($key) => in_array(explode('.',$key)[0],$prefixes,true), ARRAY_FILTER_USE_KEY))
                        @if($groupOptions)<fieldset class="staff-permission-group"><legend>{{ $groupTitle }}</legend>@foreach($groupOptions as $name=>$label)<label class="staff-choice"><input type="checkbox" name="permissions[]" value="{{ $name }}" @checked(in_array($name,$selectedPermissions,true))><span>{{ $label }}</span></label>@endforeach</fieldset>@endif
                    @endforeach</div>
                    @if(!$options)<p class="staff-hint">مجوزی برای واگذاری در دسترس شما نیست؛ ثبت خدمات کاری همچنان ممکن است.</p>@endif
                    @if($errors->has('permissions') || $errors->has('permissions.*'))<p class="staff-field-error" role="alert">{{ $errors->first('permissions') ?: $errors->first('permissions.*') }}</p>@endif
                </fieldset>
                <footer class="staff-form-actions"><a class="ownership-button" href="{{ $directoryUrl }}" data-staff-cancel>انصراف</a><button class="ownership-button ownership-button--primary" type="submit" data-staff-save>{{ $editing ? 'ذخیره تغییرات' : 'ثبت کارمند' }}</button></footer>
            </form>
        </section>
    @else
        <section class="ownership-card" data-staff-directory aria-labelledby="staff-directory-title">
            <header class="staff-section-heading"><h3 id="staff-directory-title">کارکنان {{ $building->title }}</h3><span class="staff-count">{{ $number($staff->total()) }} کارمند</span></header>
            @if($staff->isNotEmpty())
                <div class="staff-filters" data-staff-filters hidden><label class="ownership-field"><span>جست‌وجو در این صفحه</span><input type="search" placeholder="نام یا شماره موبایل" data-staff-search></label><label class="ownership-field"><span>نوع کارمند</span><select data-staff-filter-type><option value="">همه انواع</option>@foreach($types as $key=>$type)<option value="{{ $key }}">{{ $type['title'] }}</option>@endforeach</select></label><label class="ownership-field"><span>وضعیت</span><select data-staff-filter-status><option value="">همه وضعیت‌ها</option><option value="active">فعال</option><option value="inactive">غیرفعال</option></select></label></div>
                <p class="staff-hint" data-staff-result role="status" aria-live="polite"></p><p class="staff-empty" data-staff-filter-empty hidden>کارمندی با این مشخصات در این صفحه پیدا نشد.</p>
            @endif
            <div class="staff-records">@forelse($staff as $employee)
                @php($permissions = $employee->assignment->role->permissions)
                <article class="staff-record" data-staff-record data-search="{{ $employee->user->first_name.' '.$employee->user->last_name.' '.$employee->user->mobile }}" data-type="{{ $employee->employee_type }}" data-status="{{ $employee->assignment->is_active ? 'active' : 'inactive' }}">
                    <div class="staff-record__top"><div class="staff-person"><span class="staff-avatar" aria-hidden="true">{{ mb_substr($employee->user->first_name ?: $employee->user->last_name,0,1) }}</span><div><strong>{{ $employee->user->first_name }} {{ $employee->user->last_name }}</strong><bdi>{{ strtr($employee->user->mobile,$digits) }}</bdi></div></div><div class="staff-record__labels"><span class="staff-badge">{{ $types[$employee->employee_type]['title'] ?? $employee->employee_type }}</span><span class="staff-badge {{ $employee->assignment->is_active ? 'staff-badge--active' : '' }}">{{ $employee->assignment->is_active ? 'فعال' : 'غیرفعال' }}</span></div><a class="ownership-button" href="{{ $directoryUrl.'&edit='.$employee->id }}" aria-label="ویرایش {{ $employee->user->first_name.' '.$employee->user->last_name }}">@include('management.partials.icon',['name'=>'settings','size'=>16]) ویرایش</a></div>
                    <div class="staff-record__services"><span class="staff-muted">خدمات</span>@foreach($employee->services as $item)<span class="staff-service">{{ $types[$employee->employee_type]['services'][$item] ?? $item }}</span>@endforeach</div>
                    <details class="staff-record__permissions"><summary>دسترسی‌های سامانه <span>{{ $number($permissions->count()) }}</span></summary><div>@forelse($permissions as $permission)<span class="staff-service">{{ config('building_staff.permissions')[$permission->name] ?? $permission->display_name }}</span>@empty<span class="staff-muted">دسترسی سامانه‌ای واگذار نشده است.</span>@endforelse</div></details>
                </article>
            @empty<div class="staff-empty">@include('management.partials.icon',['name'=>'users','size'=>36])<h3>هنوز کارمندی ثبت نشده است</h3><p>برای ثبت مشخصات، خدمات و دسترسی‌ها، «افزودن کارمند» را بزنید.</p><a class="ownership-button ownership-button--primary" href="{{ $directoryUrl.'&create=1' }}">افزودن کارمند</a></div>@endforelse</div>
            @if($staff->hasPages())<nav class="staff-pagination" aria-label="صفحه‌بندی کارکنان">{{ $staff->links() }}</nav>@endif
        </section>
    @endif
</div>
@endsection
@push('scripts')
<script src="{{ asset('js/buildino-building-staff.js') }}?v={{ filemtime(public_path('js/buildino-building-staff.js')) }}" defer></script>
@endpush
