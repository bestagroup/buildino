@php
    $tableId =
        $id
        ?? (
            'datatable-'
            . \Illuminate\Support\Str::random(8)
        );

    $encodedColumns =
        base64_encode(
            json_encode(
                $columns ?? [],
                JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
            )
        );
@endphp

<div
    class="buildino-dt-shell"
    data-dt-shell
>
    <div class="buildino-dt-loading" data-dt-loading>
        <span class="spinner-border spinner-border-sm"></span>
        <span>در حال دریافت اطلاعات...</span>
    </div>

    <div class="table-responsive">
        <table
            id="{{ $tableId }}"
            class="table align-middle buildino-datatable js-server-datatable"
            data-dt-url="{{ $url }}"
            data-dt-columns="{{ $encodedColumns }}"
            data-dt-page-length="{{ $pageLength ?? 10 }}"
            @if (! empty($countTarget))
                data-dt-count-target="{{ $countTarget }}"
            @endif
        >
            <thead>
            <tr>
                @foreach ($columns as $column)
                    <th>
                        {{ $column['title'] ?? $column['data'] }}
                    </th>
                @endforeach
            </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</div>
