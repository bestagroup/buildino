@php
    $financeVisible = $managementUi['visible_resources'] ?? [];
    $financeGroups = collect(config('management_finance.groups', []));
    $financeCurrentGroup = $financeGroups->first(fn ($group) => in_array($currentResource, $group['resources'], true));
    $financeUrl = fn ($key) => route('management.operations.show', array_filter(['resource' => $key, 'building_id' => request()->integer('building_id') ?: null]));
@endphp
<nav class="finance-navigation" aria-label="بخش‌های مالی" data-finance-navigation>
    <div class="finance-tabs">
        @foreach ($financeCurrentGroup['resources'] ?? [] as $key)
            @if (in_array($key, $financeVisible, true))
                <a href="{{ $financeUrl($key) }}" @if ($key === $currentResource) aria-current="page" @endif>{{ config("management_crud.resources.$key.title") }}</a>
            @endif
        @endforeach
    </div>
    <details class="finance-more"><summary>همه بخش‌های مالی</summary><div class="finance-menu">
        @foreach ($financeGroups as $group)
            @if (array_intersect($group['resources'], $financeVisible))
                <section><h3>{{ $group['title'] }}</h3>
                    @foreach ($group['resources'] as $key)
                        @if (in_array($key, $financeVisible, true))
                            <a href="{{ $financeUrl($key) }}" @if ($key === $currentResource) aria-current="page" @endif>{{ config("management_crud.resources.$key.title") }}</a>
                        @endif
                    @endforeach
                </section>
            @endif
        @endforeach
    </div></details>
</nav>
@once
    @push('styles') @vite('resources/css/finance-workspace.css') @endpush
    @push('scripts') <script src="{{ asset('js/buildino-finance-navigation.js') }}?v={{ filemtime(public_path('js/buildino-finance-navigation.js')) }}" defer></script> @endpush
@endonce
