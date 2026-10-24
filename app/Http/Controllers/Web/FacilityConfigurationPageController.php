<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Building;
use App\Support\Authorization\PermissionChecker;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class FacilityConfigurationPageController extends Controller
{
    public function __invoke(Request $request, PermissionChecker $permissions): View
    {
        $user = $request->user();
        $buildings = Building::query()->orderBy('title')->get()
            ->filter(fn (Building $building): bool => $permissions->allows($user, 'facilities.view', $building))
            ->map(fn (Building $building): array => [
                'id' => $building->id, 'title' => $building->title, 'currency' => $building->currency,
                'canCreate' => $permissions->allows($user, 'facilities.create', $building),
                'canUpdate' => $permissions->allows($user, 'facilities.update', $building),
            ])->values();

        return view('management.facility-configuration', ['facilityConfig' => ['buildings' => $buildings]]);
    }
}
