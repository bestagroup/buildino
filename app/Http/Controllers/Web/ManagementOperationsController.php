<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\Web\ManagementUiContextService;
use Illuminate\Http\Response;
use Illuminate\View\View;

class ManagementOperationsController extends Controller
{
    public function index(
        ManagementUiContextService $ui
    ): View {
        $user = request()->user();

        $resources = collect(
            config('management_crud.resources', [])
        )->filter(
            fn (array $resource): bool =>
                $ui->canSeeResource(
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
                        fn (array $resource): bool =>
                            ($resource['group'] ?? null)
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
            fn (array $group): bool =>
                count(
                    $group['resources']
                ) > 0
        );

        return view(
            'management.operations.index',
            [
                'user' =>
                    request()->user(),
                'groups' =>
                    $groups,
                'resourceCount' =>
                    $resources->count(),
            ]
        );
    }

    public function show(
        string $resource,
        ManagementUiContextService $ui
    ): View {
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

        return view(
            'management.operations.resource',
            [
                'user' =>
                    request()->user(),
                'resourceKey' =>
                    $resource,
                'resource' =>
                    $configuration,
                'groups' =>
                    config(
                        'management_crud.groups',
                        []
                    ),
            ]
        );
    }
}
