@php
    $flowUi = $managementUi ?? [];
    $flowResources = [];
    foreach (['complexes', 'buildings', 'blocks', 'floors', 'units', 'users', 'roles', 'role-assignments', 'invitations'] as $flowKey) {
        if (! in_array($flowKey, $flowUi['visible_resources'] ?? [], true)) {
            continue;
        }
        $flowResource = config("management_crud.resources.{$flowKey}", []);
        $flowResources[$flowKey] = [
            'title' => $flowResource['title'] ?? '',
            'url' => route('management.operations.show', $flowKey),
        ];
    }
@endphp
<script>
    window.BuildinoFlowConfig = {{ \Illuminate\Support\Js::from([
        'actorId' => auth('web')->id(),
        'resources' => $flowResources,
        'permissions' => $flowUi['permissions'] ?? [],
        'globalPermissions' => $flowUi['global_permissions'] ?? [],
        'csrfToken' => csrf_token(),
        'setupUrl' => count(array_intersect(['buildings.create', 'blocks.create'], $flowUi['permissions'] ?? [])) ? route('management.building-setup') : null,
    ]) }};
</script>
<script src="{{ asset('js/buildino-core-flows.js') }}"></script>
