@extends('management.layouts.app')

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
    <link
        rel="stylesheet"
        href="{{ asset('css/buildino-crud.css') }}"
    >
    @vite('resources/js/management.ts')
@endpush

@section('content')
    @inertia
@endsection
