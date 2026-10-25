@foreach ($processLinks as $resourceKey => $link)
    @if ($visibleResources->has($resourceKey))
        @php($active = in_array($currentResource, $link['active_resources'] ?? [$resourceKey], true))
        <a href="{{ route('management.operations.show', $resourceKey) }}"
           class="nav-link {{ $active ? 'is-active' : '' }}"
           data-nav-key="{{ $resourceKey }}"
           @if ($active) aria-current="page" @endif>
            <span class="nav-link__icon">@include('management.partials.icon', ['name' => $link['icon']])</span>
            <span class="nav-link__label">{{ $link['title'] }}</span>
        </a>
    @endif
@endforeach
