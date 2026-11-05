@props(['title', 'description' => '', 'url' => null, 'value' => null, 'unit' => '', 'status' => null])

@if ($url)
<a class="crud-resource-card" href="{{ $url }}">
@else
<article class="crud-resource-card crud-resource-card--unavailable" aria-disabled="true">
@endif
    <div class="crud-resource-card__content">
        <strong>{{ $title }}</strong>
        @if ($description)<p>{{ $description }}</p>@endif
        @if ($value !== null)
            <span class="crud-resource-card__metric"><b>{{ $value }}</b> {{ $unit }}</span>
        @endif
    </div>
    @if ($status)
        <span class="crud-resource-card__status">{{ $status }}</span>
    @elseif ($url)
        <span class="crud-resource-card__arrow" aria-hidden="true">←</span>
    @endif
@if ($url)
</a>
@else
</article>
@endif
