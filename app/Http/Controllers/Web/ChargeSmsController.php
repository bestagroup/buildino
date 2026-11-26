<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Building;
use App\Models\ChargePeriod;
use App\Models\ChargeSmsNotice;
use App\Models\User;
use App\Services\Notifications\ChargeSmsService;
use App\Services\Security\BuildingPermissionScopeService;
use App\Support\Authorization\PermissionChecker;
use Illuminate\Http\Request;

final class ChargeSmsController extends Controller
{
    public function index(Request $request, ChargeSmsService $service)
    {
        $request->validate(['building_id' => ['nullable', 'integer', 'min:1'], 'period_id' => ['nullable', 'integer', 'min:1']]);
        $ids = app(BuildingPermissionScopeService::class)->buildingIds($request->user(), 'charge-sms.manage');
        $buildings = Building::when($ids !== null, fn ($q) => $q->whereIn('id', $ids))->orderBy('title')->get()->filter(fn ($b) => app(PermissionChecker::class)->allows($request->user(), 'charge-sms.manage', $b))->values();
        $building = $request->filled('building_id') ? $buildings->firstWhere('id', $request->integer('building_id')) : ($buildings->count() === 1 ? $buildings->first() : null);
        abort_if($request->filled('building_id') && ! $building, 403);
        $periods = $building ? ChargePeriod::where('building_id', $building->id)->whereIn('status', ['issued', 'closed'])->orderByDesc('period_start')->get() : collect();
        $period = $request->filled('period_id') ? $periods->firstWhere('id', $request->integer('period_id')) : null;
        abort_if($request->filled('period_id') && ! $period, 404);
        $recipients = $period ? $service->recipients($period) : ['rows' => [], 'unresolved' => []];
        $choices = [];
        foreach ($recipients['unresolved'] as $unit) {
            $choices[$unit->id] = User::where('is_active', true)->where('is_blocked', false)->where(function ($q) use ($unit) {
                foreach (['unitOwnershipsAsUser', 'unitOccupanciesAsUser'] as $relation) {
                    $q->orWhereHas($relation, fn ($link) => $link->where('unit_id', $unit->id)->where('is_active', true)->where(fn ($x) => $x->whereNull('starts_at')->orWhereDate('starts_at', '<=', today()))->where(fn ($x) => $x->whereNull('ends_at')->orWhereDate('ends_at', '>=', today())));
                }
            })->get(['id', 'first_name', 'last_name', 'mobile']);
        }
        $history = $period ? ChargeSmsNotice::where('charge_period_id', $period->id)->with('user')->latest()->get() : collect();
        $ready = config('notifications.sms_provider') === 'ghasedak' && trim((string) config('notifications.sms_templates.charge.notice.template')) !== '';

        return view('management.charge-sms', compact('buildings', 'building', 'periods', 'period', 'recipients', 'choices', 'history', 'ready'));
    }

    public function assign(Request $request, ChargePeriod $period, ChargeSmsService $service)
    {
        $data = $request->validate(['unit_id' => ['required', 'integer'], 'user_id' => ['required', 'integer']]);
        $service->assign($request->user(), $period, $data['unit_id'], $data['user_id']);

        return redirect()->route('management.operations.show', ['resource' => 'charge-sms', 'building_id' => $period->building_id, 'period_id' => $period->id])->with('status', 'مخاطب واحد ثبت شد.');
    }

    public function send(Request $request, ChargePeriod $period, ChargeSmsService $service)
    {
        $data = $request->validate(['user_id' => ['required', 'integer']]);
        $notice = $service->send($request->user(), $period, $data['user_id']);

        return response()->json(['status' => $notice->status, 'id' => $notice->id]);
    }
}
