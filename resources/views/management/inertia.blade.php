@extends(request()->boolean('glance_embed') && request()->integer('unit_id') > 0 && (in_array(request()->route('resource'), ['invoices', 'invoice-installments', 'payments'], true) || (request()->route('resource') === 'expenses' && request()->boolean('history'))) ? 'management.layouts.unit-workspace' : 'management.layouts.app')

@section(
    'title',
    data_get($page, 'props.resource.title', 'عملیات')
        . ' | Buildino'
)
@section(
    'page-title',
    data_get($page, 'props.resource.title', 'عملیات')
)
@section(
    'page-subtitle',
    data_get($page, 'props.resource.description', '')
)

@push('head')
    @inertiaHead
@endpush

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/buildino-unit-ownerships.css') }}">
    <link rel="stylesheet" href="{{ asset('css/buildino-building-structure.css') }}?v={{ filemtime(public_path('css/buildino-building-structure.css')) }}">
    <link
        rel="stylesheet"
        href="{{ asset('css/buildino-crud.css') }}"
    >
    @vite('resources/js/management.ts')
@endpush

@section('content')
    @inertia
@endsection
