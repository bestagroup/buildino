<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;
use Illuminate\View\View;

class ManagementOperationsController extends Controller
{
    public function index(): View
    {
        $resources = collect(
            config('management_crud.resources', [])
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
        string $resource
    ): View {
        $configuration =
            config(
                "management_crud.resources.{$resource}"
            );

        abort_unless(
            is_array($configuration),
            Response::HTTP_NOT_FOUND
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
