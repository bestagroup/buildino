<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Building;
use App\Support\Authorization\PermissionChecker;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class ChargePeriodPageController extends Controller
{
    public function __invoke(Request $request, PermissionChecker $permissions): View
    {
        $user = $request->user();
        abort_unless($permissions->allowsAnyScope($user, 'charge-periods.view'), 403);

        $buildings = Building::query()->orderBy('title')->get()
            ->filter(fn (Building $building): bool => $permissions->allows($user, 'charge-periods.view', $building))
            ->map(function (Building $building) use ($user, $permissions): array {
                $capabilities = [];
                foreach (['create', 'update', 'calculate', 'issue'] as $action) {
                    $capabilities['can'.ucfirst($action)] = $permissions->allows($user, 'charge-periods.'.$action, $building);
                }

                return [
                    'id' => $building->id,
                    'title' => $building->title,
                    ...$capabilities,
                    'canViewFormulas' => $permissions->allows($user, 'charge-formulas.view', $building),
                    'canViewInvoices' => $permissions->allows($user, 'invoices.view', $building),
                ];
            })->values();

        return view('management.charge-periods', ['periodConfig' => [
            'buildings' => $buildings,
            'initialBuildingId' => $request->integer('building_id'),
        ]]);
    }
}
