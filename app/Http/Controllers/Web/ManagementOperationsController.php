<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\Web\ManagementUiContextService;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class ManagementOperationsController extends Controller
{
    public function index(
        ManagementUiContextService $ui
    ): View {
        $user = request()->user();

        $resources = collect(
            config('management_crud.resources', [])
        )->filter(
            fn (array $resource): bool => $ui->canSeeResource(
                $user,
                $resource
            )
        );

        $groups = collect(
            config('management_crud.groups', [])
        )->map(function (
            array $group,
            string $key
        ) use ($resources): array {
            $group['key'] = $key;
            $group['resources'] =
                $resources
                    ->filter(
                        fn (array $resource): bool => ($resource['group'] ?? null)
                            === $key
                    )
                    ->map(
                        fn (
                            array $resource,
                            string $resourceKey
                        ): array => [
                            ...$resource,
                            'key' => $resourceKey,
                        ]
                    )
                    ->values()
                    ->all();

            return $group;
        })->filter(
            fn (array $group): bool => count(
                $group['resources']
            ) > 0
        );

        return view(
            'management.operations.index',
            [
                'user' => request()->user(),
                'groups' => $groups,
                'resourceCount' => $resources->count(),
            ]
        );
    }

    public function show(
        string $resource,
        ManagementUiContextService $ui
    ): View|InertiaResponse {
        $configuration =
            config(
                "management_crud.resources.{$resource}"
            );

        abort_unless(
            is_array($configuration),
            Response::HTTP_NOT_FOUND
        );

        abort_unless(
            $ui->canSeeResource(
                request()->user(),
                $configuration
            ),
            Response::HTTP_FORBIDDEN
        );

        $configuration = $ui->resourceForUser(
            request()->user(),
            $configuration
        );

        $inertiaResources = config(
            'management_ui.inertia_resources',
            []
        );

        if (
            in_array(
                $resource,
                $inertiaResources,
                true
            )
        ) {
            $groups = config(
                'management_crud.groups',
                []
            );
            $visibleResources = $ui->context(
                request()->user()
            )['visible_resources'] ?? [];

            $siblingResources = collect(
                config(
                    'management_crud.resources',
                    []
                )
            )
                ->filter(
                    fn (
                        array $item,
                        string $key
                    ): bool => ($item['group'] ?? null)
                            === ($configuration['group'] ?? null)
                        && in_array(
                            $key,
                            $visibleResources,
                            true
                        )
                )
                ->map(
                    fn (
                        array $item,
                        string $key
                    ): array => [
                        'key' => $key,
                        'title' => $item['title'],
                        'url' => route(
                            'management.operations.show',
                            $key
                        ),
                        'inertia' => in_array(
                            $key,
                            $inertiaResources,
                            true
                        ),
                    ]
                )
                ->values()
                ->all();

            return Inertia::render(
                'management/operations/Resource',
                [
                    'resourceKey' => $resource,
                    'resource' => $configuration,
                    'groupTitle' => data_get(
                        $groups,
                        ($configuration['group'] ?? '')
                            .'.title',
                        'Operations'
                    ),
                    'siblingResources' => $siblingResources,
                    'operationsUrl' => route(
                        'management.operations.index'
                    ),
                    'lookupBase' => url(
                        '/management/lookups'
                    ),
                    'csrfToken' => csrf_token(),
                ]
            );
        }

        return view(
            'management.operations.resource',
            [
                'user' => request()->user(),
                'resourceKey' => $resource,
                'resource' => $configuration,
                'groups' => config(
                    'management_crud.groups',
                    []
                ),
            ]
        );
    }
}
