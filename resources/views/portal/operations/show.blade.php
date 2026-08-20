@extends('portal.layouts.app')

@section(
    'title',
    ($detail['title'] ?? 'جزئیات')
    . ' | Buildino'
)

@section(
    'page-title',
    'جزئیات '
    . (
        $operationConfig['title']
        ?? ''
    )
)

@section('sidebar-links')
    @php
        $resources =
            config(
                "portal_operations.{$area}",
                []
            );
    @endphp

    @foreach ($resources as $key => $item)
        <a
            href="{{
                route(
                    "portal.{$area}.operations.index",
                    [
                        'resource' => $key,
                    ]
                )
            }}"
            class="{{
                $resource === $key
                    ? 'is-active'
                    : ''
            }}"
        >
            @include(
                'management.partials.icon',
                [
                    'name' =>
                        $item['icon']
                        ?? 'grid',
                    'size' => 18,
                ]
            )
            <span>
                {{ $item['title'] }}
            </span>
        </a>
    @endforeach
@endsection

@section('content')
<nav class="portal-breadcrumb">
    <a
        href="{{
            route(
                "portal.{$area}.dashboard"
            )
        }}"
    >
        داشبورد
    </a>

    <span>/</span>

    <a
        href="{{
            route(
                "portal.{$area}.operations.index",
                [
                    'resource' =>
                        $resource,
                ]
            )
        }}"
    >
        {{
            $operationConfig['title']
            ?? 'عملیات'
        }}
    </a>

    <span>/</span>
    <strong>جزئیات</strong>
</nav>

<section class="portal-detail-hero">
    <div>
        <span class="portal-eyebrow">
            OPERATION DETAIL
        </span>

        <h2>
            {{ $detail['title'] }}
        </h2>

        <p>
            {{
                $detail['subtitle']
                ?? ''
            }}
        </p>
    </div>

    <span class="portal-status portal-status--{{
        $detail['status_tone']
        ?? 'info'
    }}">
        {{
            $detail['status']
            ?? '—'
        }}
    </span>
</section>

<section class="portal-detail-facts">
    @foreach (
        $detail['facts']
        ?? []
        as $label => $value
    )
        <article>
            <span>
                {{ $label }}
            </span>

            <strong>
                {{ $value }}
            </strong>
        </article>
    @endforeach
</section>

@foreach (
    $detail['sections']
    ?? []
    as $section
)
    <section class="portal-section">
        <div class="portal-section__heading">
            <div>
                <span class="portal-eyebrow">
                    DETAIL
                </span>
                <h3>
                    {{ $section['title'] }}
                </h3>
            </div>
        </div>

        @if (
            ($section['type'] ?? '')
            === 'table'
        )
            <div class="portal-table-card">
                <div class="table-responsive">
                    <table class="table portal-table align-middle">
                        <thead>
                        <tr>
                            @foreach (
                                $section['columns']
                                ?? []
                                as $column
                            )
                                <th>
                                    {{ $column }}
                                </th>
                            @endforeach
                        </tr>
                        </thead>

                        <tbody>
                        @forelse (
                            $section['rows']
                            ?? []
                            as $row
                        )
                            <tr>
                                @foreach ($row as $cell)
                                    <td>
                                        {{ $cell }}
                                    </td>
                                @endforeach
                            </tr>
                        @empty
                            <tr>
                                <td
                                    colspan="{{
                                        count(
                                            $section['columns']
                                            ?? []
                                        )
                                    }}"
                                    class="portal-table-empty"
                                >
                                    رکوردی وجود ندارد.
                                </td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @elseif (
            ($section['type'] ?? '')
            === 'timeline'
        )
            <div class="portal-detail-timeline">
                @forelse (
                    $section['rows']
                    ?? []
                    as $row
                )
                    <article>
                        <span class="portal-detail-timeline__dot"></span>

                        <div>
                            <strong>
                                {{
                                    $row['title']
                                    ?? 'رویداد'
                                }}
                            </strong>

                            <p>
                                {{
                                    $row['meta']
                                    ?? ''
                                }}
                            </p>

                            <time>
                                {{
                                    $row['time']
                                    ?? '—'
                                }}
                            </time>

                            @if (! empty($row['url']))
                                <a
                                    href="{{ $row['url'] }}"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="portal-section-link"
                                >
                                    دریافت رسید PDF
                                </a>
                            @endif
                        </div>
                    </article>
                @empty
                    <div class="portal-empty-state">
                        رویدادی ثبت نشده است.
                    </div>
                @endforelse
            </div>
        @endif
    </section>
@endforeach
@endsection
