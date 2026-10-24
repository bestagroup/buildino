<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Building;
use App\Support\Authorization\PermissionChecker;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class GuestVisitPageController extends Controller
{
    public function __invoke(Request $request, PermissionChecker $permissions): View
    {
        $user = $request->user();
        $buildings = Building::query()->orderBy('title')->get()
            ->filter(fn (Building $building): bool => $permissions->allows($user, 'guest-visits.view', $building))
            ->map(fn (Building $building): array => [
                'id' => $building->id,
                'title' => $building->title,
                'canCreate' => $permissions->allows($user, 'guest-visits.create', $building),
                'canUpdate' => $permissions->allows($user, 'guest-visits.update', $building),
            ])->values();

        return view('management.guest-visits', ['guestConfig' => ['buildings' => $buildings]]);
    }
}
