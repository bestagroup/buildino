@foreach(['services'=>'کارهای من', 'wallet'=>'گردش کیف پول', 'payouts'=>'درخواست‌های تسویه'] as $key=>$label)
<a href="{{ route('portal.provider.operations.index', ['resource'=>$key]) }}" @class(['is-active'=>($resource ?? '') === $key]) @if(($resource ?? '') === $key) aria-current="page" @endif>
    @include('management.partials.icon', ['name'=>config('portal_operations.provider.'.$key.'.icon', 'grid'),'size'=>19])<span>{{ $label }}</span>
</a>
@endforeach
