@extends('management.layouts.app')
@section('title', 'ثبت کارمندان ساختمان')
@section('content')
<div class="ownership-page">
<h2>ثبت کارمندان ساختمان</h2>
@if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
@if($errors->any())<div class="alert alert-danger"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
@if($buildings->count()>1)
<form method="GET"><input type="hidden" name="resource" value="building-staff"><label>ساختمان<select name="building_id" class="form-select" onchange="this.form.submit()"><option value="">انتخاب ساختمان</option>@foreach($buildings as $item)<option value="{{ $item->id }}" @selected($building?->id===$item->id)>{{ $item->title }}</option>@endforeach</select></label><button class="btn btn-secondary">نمایش</button></form>
@endif
@if($building)
<section class="ownership-card">
<h3>{{ $building->title }} — {{ $editing ? 'ویرایش کارمند' : 'ثبت کارمند' }}</h3>
<p>ورود با شماره موبایل و کد یک‌بارمصرف است. حساب موجود با همین شماره متصل می‌شود و مشخصات و دسترسی‌های قبلی آن تغییر نمی‌کند. خدمات کاری به‌تنهایی مجوز سامانه ایجاد نمی‌کند.</p>
<form method="POST" action="{{ $editing ? route('management.building-staff.update',[$building,$editing]) : route('management.building-staff.store',$building) }}">
@csrf @if($editing) @method('PATCH') @endif
@if($editing)<p>{{ $editing->user->first_name }} {{ $editing->user->last_name }} — {{ $editing->user->mobile }}</p>@else
<div class="row g-3">
@foreach(['first_name'=>'نام','last_name'=>'نام خانوادگی','mobile'=>'شماره موبایل'] as $field=>$label)
<div class="col-md-4"><label class="ownership-field"><span>{{ $label }}</span><input class="form-control" name="{{ $field }}" value="{{ old($field) }}" required maxlength="{{ $field==='mobile' ? 11 : 100 }}" @if($field==='mobile') inputmode="tel" placeholder="09123456789" @endif></label></div>
@endforeach
</div>
@endif
@php($selectedType=old('employee_type',$editing?->employee_type ?? 'concierge'))
<label class="ownership-field"><span>نوع کارمند</span><select class="form-select" name="employee_type" id="staff-type">@foreach(config('building_staff.types') as $key=>$type)<option value="{{ $key }}" @selected($selectedType===$key)>{{ $type['title'] }}</option>@endforeach</select></label>
<fieldset><legend>خدمات قابل ارائه</legend>
@foreach(config('building_staff.types') as $key=>$type)
<div data-staff-services="{{ $key }}" @if($selectedType!==$key) hidden @endif>
@foreach($type['services'] as $value=>$label)<label class="d-inline-block p-2"><input type="checkbox" name="services[]" value="{{ $value }}" @disabled($selectedType!==$key) @checked(in_array($value,old('services',$editing?->services ?? [])))> {{ $label }}</label>@endforeach
</div>
@endforeach
</fieldset>
<fieldset><legend>دسترسی‌های سامانه در همین ساختمان</legend>
@foreach($options as $name=>$label)<label class="d-inline-block p-2"><input type="checkbox" name="permissions[]" value="{{ $name }}" @checked(in_array($name,old('permissions',$editing?->assignment->role->permissions->pluck('name')->all() ?? [])))> {{ $label }}</label>@endforeach
</fieldset>
<label class="ownership-field"><span>وضعیت دسترسی کارمند</span><select name="is_active" class="form-select"><option value="1" @selected((int)old('is_active',$editing?->assignment->is_active ?? 1)===1)>فعال</option><option value="0" @selected((int)old('is_active',$editing?->assignment->is_active ?? 1)===0)>غیرفعال</option></select></label>
<button class="btn btn-primary" type="submit">ذخیره کارمند</button>
@if($editing)<a href="{{ route('management.operations.show',['resource'=>'building-staff','building_id'=>$building->id]) }}" class="btn btn-secondary">انصراف</a>@endif
</form></section>
<section class="ownership-card"><h3>کارمندان ساختمان</h3><div class="table-responsive"><table class="table"><thead><tr><th>نام و نام خانوادگی</th><th>موبایل</th><th>نوع کارمند</th><th>خدمات</th><th>مجوزها</th><th>وضعیت</th><th>عملیات</th></tr></thead><tbody>
@forelse($staff as $employee)
<tr><td>{{ $employee->user->first_name }} {{ $employee->user->last_name }}</td><td>{{ $employee->user->mobile }}</td><td>{{ config('building_staff.types.'.$employee->employee_type.'.title') }}</td><td>@foreach($employee->services as $item){{ config('building_staff.types.'.$employee->employee_type.'.services.'.$item) }}@if(!$loop->last)، @endif @endforeach</td><td>{{ $employee->assignment->role->permissions->map(fn($p)=>(config('building_staff.permissions')[$p->name] ?? null) ?? $p->display_name)->implode('، ') }}</td><td>{{ $employee->assignment->is_active ? 'فعال' : 'غیرفعال' }}</td><td><a href="{{ route('management.operations.show',['resource'=>'building-staff','building_id'=>$building->id,'edit'=>$employee->id]) }}">ویرایش / تغییر دسترسی</a></td></tr>
@empty<tr><td colspan="7">کارمندی ثبت نشده است.</td></tr>@endforelse
</tbody></table></div>{{ $staff->links() }}</section>
@else<p>برای ثبت کارمند، ساختمان دارای دسترسی را انتخاب کنید.</p>@endif
</div>
@endsection
@push('scripts')
<script>
document.getElementById('staff-type')?.addEventListener('change', function () {
    document.querySelectorAll('[data-staff-services]').forEach(group => {
        const active = group.dataset.staffServices === this.value;
        group.hidden = !active;
        group.querySelectorAll('input').forEach(input => { input.disabled = !active; });
    });
});
</script>
@endpush
