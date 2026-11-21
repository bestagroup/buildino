@php
    $currentResource = $resource ?? '';
    $links = ['units'=>'واحدهای من', 'invoices'=>'صورتحساب‌ها', 'wallet'=>'کیف پول', 'guests'=>'مهمان‌ها', 'reservations'=>'رزرو امکانات', 'services'=>'خدمات', 'support'=>'پشتیبانی', 'loyalty'=>'باشگاه وفاداری'];
@endphp
@foreach($links as $key => $label)
    <a href="{{ route('portal.resident.operations.index', ['resource'=>$key]) }}" @class(['is-active'=>$currentResource === $key]) @if($currentResource === $key) aria-current="page" @endif>
        @include('management.partials.icon', ['name'=>config('portal_operations.resident.'.$key.'.icon', 'building'), 'size'=>18])<span>{{ $label }}</span>
    </a>
@endforeach
<button type="button" class="portal-invitation-link" data-invitation-open>@include('management.partials.icon', ['name'=>'plus','size'=>18])<span>اتصال به واحد با کد دعوت</span></button>
