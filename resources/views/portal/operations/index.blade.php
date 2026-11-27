@extends('portal.layouts.app')
@section('title', ($operationConfig['title'] ?? 'عملیات').' | Buildino')
@section('page-title', $operationConfig['title'] ?? 'عملیات')
@section('content')
@if($area === 'resident')
    @include('portal.resident.work-index')
@else
    @include('portal.provider.work-index')
@endif
@endsection
