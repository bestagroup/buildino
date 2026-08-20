@extends('management.layouts.app')

@section('title', $resource['title'] . ' | Buildino')
@section('page-title', $resource['title'])
@section('page-subtitle', $resource['description'] ?? 'مدیریت اطلاعات سامانه')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/buildino-crud.css') }}">
@endpush

@php
    $visibleResourceKeys =
        $managementUi['visible_resources']
        ?? [];

    $siblingResources =
        collect(
            config(
                'management_crud.resources',
                []
            )
        )
            ->filter(
                fn (
                    array $item,
                    string $key
                ): bool =>
                    ($item['group'] ?? null)
                        === ($resource['group'] ?? null)
                    && in_array(
                        $key,
                        $visibleResourceKeys,
                        true
                    )
            );
@endphp

@section('content')
<div
    class="crud-page buildino-resource-page"
    id="buildinoCrudApp"
    data-resource="{{ $resourceKey }}"
>
    <div class="crud-breadcrumb">
        <a href="{{ route('management.dashboard') }}">
            داشبورد
        </a>
        <span>/</span>
        <a href="{{ route('management.operations.index') }}">
            عملیات
        </a>
        <span>/</span>
        <strong>{{ $resource['title'] }}</strong>
    </div>

    <section class="crud-header card mb-4">
        <div class="crud-header__copy">
            <span class="eyebrow">
                {{ $groups[$resource['group']]['title'] ?? 'Operations' }}
            </span>
            <h2>{{ $resource['title'] }}</h2>
            <p>
                {{ $resource['description'] ?? '' }}
            </p>

            @if (! empty($resource['note']))
                <div class="crud-inline-note">
                    {{ $resource['note'] }}
                </div>
            @endif
        </div>

        <div class="crud-header__actions">
            <a
                href="{{ route('management.operations.index') }}"
                class="crud-button crud-button--soft btn btn-label-secondary"
            >
                همه ماژول‌ها
            </a>

            @if (! empty($resource['create']))
                <button
                    type="button"
                    class="crud-button crud-button--primary btn btn-primary"
                    id="crudCreateButton"
                >
                    + ثبت رکورد جدید
                </button>
            @endif
        </div>
    </section>

    @if ($siblingResources->count() > 1)
        <nav
            class="crud-sibling-nav card mb-4"
            aria-label="زیرماژول‌های مرتبط"
        >
            @foreach ($siblingResources as $key => $item)
                <a
                    href="{{
                        route(
                            'management.operations.show',
                            $key
                        )
                    }}"
                    class="{{
                        $key === $resourceKey
                            ? 'is-active'
                            : ''
                    }}"
                >
                    {{ $item['title'] }}
                </a>
            @endforeach
        </nav>
    @endif

    @if (! empty($resource['context']))
        <section class="crud-context-card card mb-4">
            <div class="crud-context-card__heading">
                <strong>محدوده عملیات</strong>
                <span>
                    برای بارگذاری داده‌ها، مقادیر لازم را انتخاب کنید.
                </span>
            </div>

            <div
                class="crud-context-grid"
                id="crudContextFields"
            >
                @foreach ($resource['context'] as $context)
                    <label class="crud-field">
                        <span>
                            {{ $context['label'] }}
                            @if ($context['required'] ?? false)
                                <b>*</b>
                            @endif
                        </span>

                        <select
                            class="form-select"
                            data-context-name="{{ $context['name'] }}"
                            data-lookup="{{ $context['lookup'] }}"
                            data-required="{{ ($context['required'] ?? false) ? '1' : '0' }}"
                            @if (! empty($context['depends_on']))
                                data-depends-on="{{ $context['depends_on'] }}"
                            @endif
                        >
                            <option value="">
                                انتخاب کنید
                            </option>
                        </select>
                    </label>
                @endforeach
            </div>
        </section>
    @endif

    @if (($resource['mode'] ?? 'table') === 'singleton')
        <section class="crud-panel crud-singleton-panel card">
            <div class="crud-panel__header">
                <div>
                    <h3>تنظیمات</h3>
                    <p>
                        مقدار فعلی را بارگذاری، ویرایش و ذخیره کنید.
                    </p>
                </div>

                <button
                    type="button"
                    class="crud-button crud-button--soft btn btn-label-secondary"
                    id="crudLoadSingleton"
                >
                    بارگذاری
                </button>
            </div>

            <form
                class="crud-form crud-form--inline"
                id="crudSingletonForm"
                autocomplete="off"
            >
                <div
                    class="crud-form-grid"
                    id="crudSingletonFields"
                ></div>

                <div class="crud-form-actions">
                    <button
                        type="submit"
                        class="crud-button crud-button--primary btn btn-primary"
                    >
                        ذخیره تنظیمات
                    </button>
                </div>
            </form>
        </section>
    @else
        <section class="crud-panel card">
            <div class="crud-toolbar">
                <div class="crud-search">
                    <input
                        type="search"
                        class="form-control"
                        id="crudSearch"
                        placeholder="جستجو در رکوردهای بارگذاری‌شده..."
                    >
                </div>

                <div class="crud-toolbar__actions">
                    <select id="crudPageSize" class="form-select">
                        <option value="10">۱۰ رکورد</option>
                        <option value="25" selected>۲۵ رکورد</option>
                        <option value="50">۵۰ رکورد</option>
                        <option value="100">۱۰۰ رکورد</option>
                    </select>

                    <button
                        type="button"
                        class="crud-button crud-button--soft btn btn-label-secondary"
                        id="crudRefreshButton"
                    >
                        بروزرسانی
                    </button>
                </div>
            </div>

            <div
                class="crud-state"
                id="crudState"
            >
                <div class="crud-state__loader"></div>
                <span>در حال بارگذاری...</span>
            </div>

            <div
                class="crud-table-wrap"
                id="crudTableWrap"
                hidden
            >
                <table class="crud-table table table-hover align-middle mb-0">
                    <thead>
                        <tr id="crudTableHead"></tr>
                    </thead>
                    <tbody id="crudTableBody"></tbody>
                </table>
            </div>

            <div
                class="crud-pagination"
                id="crudPagination"
                hidden
            >
                <span id="crudRecordSummary"></span>

                <div>
                    <button
                        type="button"
                        id="crudPrevPage"
                        class="crud-page-button"
                    >
                        قبلی
                    </button>
                    <strong id="crudCurrentPage">1</strong>
                    <button
                        type="button"
                        id="crudNextPage"
                        class="crud-page-button"
                    >
                        بعدی
                    </button>
                </div>
            </div>
        </section>
    @endif

    <div
        class="crud-drawer-backdrop"
        id="crudDrawerBackdrop"
    ></div>

    <aside
        class="crud-drawer"
        id="crudDrawer"
        aria-hidden="true"
    >
        <div class="crud-drawer__header">
            <div>
                <span class="eyebrow" id="crudDrawerEyebrow">
                    Create
                </span>
                <h3 id="crudDrawerTitle">
                    ثبت رکورد
                </h3>
            </div>

            <button
                type="button"
                class="crud-close-button"
                id="crudDrawerClose"
                aria-label="بستن"
            >
                ×
            </button>
        </div>

        <form
            id="crudForm"
            class="crud-form"
            autocomplete="off"
        >
            <input
                type="hidden"
                id="crudRecordId"
            >

            <div
                class="crud-form-grid"
                id="crudFormFields"
            ></div>

            <div class="crud-form-error" id="crudFormError"></div>

            <div class="crud-form-actions">
                <button
                    type="button"
                    class="crud-button crud-button--soft btn btn-label-secondary"
                    id="crudCancelButton"
                >
                    انصراف
                </button>

                <button
                    type="submit"
                    class="crud-button crud-button--primary btn btn-primary"
                    id="crudSaveButton"
                >
                    ذخیره
                </button>
            </div>
        </form>
    </aside>

    <div
        class="crud-modal-backdrop"
        id="crudActionBackdrop"
    ></div>

    <div
        class="crud-modal"
        id="crudActionModal"
        aria-hidden="true"
    >
        <div class="crud-modal__header">
            <div>
                <span class="eyebrow">
                    Workflow Action
                </span>
                <h3 id="crudActionTitle">
                    عملیات
                </h3>
            </div>

            <button
                type="button"
                class="crud-close-button"
                id="crudActionClose"
            >
                ×
            </button>
        </div>

        <form id="crudActionForm">
            <div
                class="crud-form-grid"
                id="crudActionFields"
            ></div>

            <div
                class="crud-form-error"
                id="crudActionError"
            ></div>

            <div class="crud-form-actions">
                <button
                    type="button"
                    class="crud-button crud-button--soft btn btn-label-secondary"
                    id="crudActionCancel"
                >
                    انصراف
                </button>

                <button
                    type="submit"
                    class="crud-button crud-button--primary btn btn-primary"
                    id="crudActionSubmit"
                >
                    اجرا
                </button>
            </div>
        </form>
    </div>

    <div
        class="crud-toast-stack"
        id="crudToastStack"
        aria-live="polite"
    ></div>
</div>
@endsection

@push('scripts')
<script>
    window.BuildinoCrud = {
        resourceKey: @json($resourceKey),
        resource: {{ \Illuminate\Support\Js::from($resource) }},
        lookupBase: @json(url('/management/lookups')),
        csrfToken: @json(csrf_token())
    };
</script>
<script
    src="{{ asset('js/buildino-crud.js') }}"
    defer
></script>
@endpush
