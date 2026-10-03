<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Building;
use App\Models\BuildingSubscription;
use App\Models\Plan;
use App\Services\Subscriptions\SubscriptionService;
use App\Services\Web\ManagementDashboardAccessService;
use App\Support\Authorization\PermissionChecker;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

final class ManagementSubscriptionController extends Controller
{
    public function __construct(
        private readonly PermissionChecker $permissions,
        private readonly SubscriptionService $subscriptions,
        private readonly ManagementDashboardAccessService $access
    ) {
    }

    public function index(Request $request): View
    {
        $user = $request->user();

        abort_unless(
            $this->permissions->allowsAnyScope($user, 'subscriptions.view'),
            403
        );

        $buildings = $this->access
            ->accessibleBuildings($user)
            ->load([
                'buildingSubscriptions' => fn ($query) => $query
                    ->with('plan')
                    ->latest('starts_at')
                    ->latest('id'),
            ]);

        $plans = Plan::query()
            ->where('is_active', true)
            ->with('features')
            ->orderBy('price')
            ->orderBy('id')
            ->get();

        return view('management.subscriptions.index', [
            'buildings' => $buildings,
            'plans' => $plans,
        ]);
    }

    public function activate(Request $request, Building $building): RedirectResponse
    {
        $this->authorizeManagement($request, $building);

        $validated = $request->validate([
            'plan_id' => ['required', 'integer', 'exists:plans,id'],
            'starts_at' => ['nullable', 'date'],
            'duration_days' => ['nullable', 'integer', 'min:1', 'max:3660'],
            'grace_days' => ['nullable', 'integer', 'min:0', 'max:90'],
        ]);

        try {
            $this->subscriptions->activate(
                $building,
                Plan::query()->findOrFail((int) $validated['plan_id']),
                $request->user(),
                isset($validated['starts_at'])
                    ? Carbon::parse($validated['starts_at'])
                    : null,
                $validated['duration_days'] ?? null,
                (int) ($validated['grace_days'] ?? 7)
            );
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors([
                'subscription' => $exception->getMessage(),
            ]);
        }

        return back()->with('status', 'اشتراک ساختمان با موفقیت فعال شد.');
    }

    public function renew(
        Request $request,
        BuildingSubscription $buildingSubscription
    ): RedirectResponse {
        $buildingSubscription->loadMissing('building');

        $this->authorizeManagement($request, $buildingSubscription->building);

        $validated = $request->validate([
            'duration_days' => ['nullable', 'integer', 'min:1', 'max:3660'],
            'grace_days' => ['nullable', 'integer', 'min:0', 'max:90'],
        ]);

        try {
            $this->subscriptions->renew(
                $buildingSubscription,
                $request->user(),
                $validated['duration_days'] ?? null,
                (int) ($validated['grace_days'] ?? 7)
            );
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors([
                'subscription' => $exception->getMessage(),
            ]);
        }

        return back()->with('status', 'اشتراک با موفقیت تمدید شد.');
    }

    public function suspend(
        Request $request,
        BuildingSubscription $buildingSubscription
    ): RedirectResponse {
        $buildingSubscription->loadMissing('building');

        $this->authorizeManagement($request, $buildingSubscription->building);

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $this->subscriptions->suspend(
            $buildingSubscription,
            $request->user(),
            $validated['reason'] ?? null
        );

        return back()->with('status', 'اشتراک ساختمان تعلیق شد.');
    }

    public function cancel(
        Request $request,
        BuildingSubscription $buildingSubscription
    ): RedirectResponse {
        $buildingSubscription->loadMissing('building');

        $this->authorizeManagement($request, $buildingSubscription->building);

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $this->subscriptions->cancel(
            $buildingSubscription,
            $request->user(),
            $validated['reason'] ?? null
        );

        return back()->with('status', 'اشتراک ساختمان لغو شد.');
    }

    private function authorizeManagement(Request $request, Building $building): void
    {
        abort_unless(
            $this->permissions->allows(
                $request->user(),
                'subscriptions.manage',
                $building
            ),
            403
        );
    }
}
