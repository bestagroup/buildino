@extends('management.layouts.app')

@section('title', 'مرکز عملیات Buildino')
@section('page-title', 'عملیات و فرم‌های سامانه')
@section('page-subtitle', 'ثبت، ویرایش و اجرای فرآیندهای عملیاتی روی Backend واقعی')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/buildino-crud.css') }}">
@endpush

@section('content')
<section class="crud-hero">
    <div>
        <span class="eyebrow">Operational Web UI</span>
        <h2>مرکز عملیات Buildino</h2>
        <p>
            از این بخش می‌توانید اطلاعات پایه و فرآیندهای عملیاتی سامانه را
            بدون نیاز به Postman مستقیماً از طریق فرم‌های وب مدیریت کنید.
        </p>
    </div>

    <div class="crud-hero__stats">
        <div>
            <strong>{{ number_format($resourceCount) }}</strong>
            <span>صفحه عملیاتی</span>
        </div>
        <div>
            <strong>{{ number_format($groups->count()) }}</strong>
            <span>حوزه مدیریتی</span>
        </div>
    </div>
</section>

<div class="crud-groups">
    @foreach ($groups as $group)
        <section class="crud-group">
            <div class="crud-group__heading">
                <div class="crud-group__icon">
                    @include(
                        'management.partials.icon',
                        [
                            'name' => $group['icon'] ?? 'tools',
                            'size' => 22,
                        ]
                    )
                </div>

                <div>
                    <h3>{{ $group['title'] }}</h3>
                    <p>{{ $group['description'] ?? '' }}</p>
                </div>
            </div>

            <div class="crud-resource-grid">
                @foreach ($group['resources'] as $resource)
                    <a
                        class="crud-resource-card"
                        href="{{
                            route(
                                'management.operations.show',
                                $resource['key']
                            )
                        }}"
                    >
                        <div>
                            <strong>
                                {{ $resource['title'] }}
                            </strong>

                            <p>
                                {{ $resource['description'] ?? '' }}
                            </p>
                        </div>

                        <span class="crud-resource-card__arrow">
                            ←
                        </span>
                    </a>
                @endforeach
            </div>
        </section>
    @endforeach
</div>
@endsection
