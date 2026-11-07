<?php

namespace App\Http\Controllers\Web;

use App\Enums\InvitationStatus;
use App\Http\Controllers\Controller;
use App\Models\Building;
use App\Models\Unit;
use App\Models\UnitInvitation;
use App\Models\User;
use App\Services\UnitInvitationService;
use App\Support\Authorization\PermissionChecker;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class BuildingInvitationPageController extends Controller
{
    private function pairs(int $buildingId)
    {
        $links = null;
        foreach (['unit_ownerships', 'unit_occupancies'] as $table) {
            $query = DB::table($table)->select('unit_id', 'user_id')->where('is_active', true)
                ->where(fn ($q) => $q->whereNull('starts_at')->orWhereDate('starts_at', '<=', today()))
                ->where(fn ($q) => $q->whereNull('ends_at')->orWhereDate('ends_at', '>=', today()));
            $links = $links ? $links->union($query) : $query;
        }

        return DB::query()->fromSub($links, 'links')
            ->join('units', 'units.id', '=', 'links.unit_id')
            ->join('floors', 'floors.id', '=', 'units.floor_id')
            ->join('blocks', 'blocks.id', '=', 'floors.block_id')
            ->join('users', 'users.id', '=', 'links.user_id')
            ->where('blocks.building_id', $buildingId)
            ->select('links.unit_id', 'links.user_id', 'units.unit_number', 'users.first_name', 'users.last_name', 'users.mobile', 'users.is_active', 'users.is_blocked');
    }

    public function index(Request $request, PermissionChecker $permissions)
    {
        $buildings = Building::orderBy('title')->get()->filter(fn ($building) => $permissions->allows($request->user(), 'unit-invitations.view', $building))->values();
        abort_if($buildings->isEmpty(), 403);
        $id = $request->integer('building_id');
        if (! $id && $request->integer('unit_id')) {
            $id = (int) Unit::with('floor.block')->find($request->integer('unit_id'))?->floor?->block?->building_id;
        }
        $building = $id ? $buildings->firstWhere('id', $id) : ($buildings->count() === 1 ? $buildings->first() : null);
        abort_if($id && ! $building, 403);
        $recipients = $building ? $this->pairs($building->id)->orderBy('units.unit_number')->orderBy('users.id')->paginate(30, ['*'], 'recipients_page')->withQueryString() : null;
        $invitations = $building ? UnitInvitation::whereHas('unit.floor.block', fn ($q) => $q->where('building_id', $building->id))
            ->with(['unit', 'invitedUser'])->latest('id')->paginate(30, ['*'], 'history_page')->withQueryString() : null;

        return view('management.building-invitations', [
            'buildings' => $buildings, 'building' => $building, 'recipients' => $recipients, 'invitations' => $invitations,
            'canCreate' => $building && $permissions->allows($request->user(), 'unit-invitations.create', $building),
            'canUpdate' => $building && $permissions->allows($request->user(), 'unit-invitations.update', $building),
        ]);
    }

    public function send(Request $request, Building $building, PermissionChecker $permissions, UnitInvitationService $service)
    {
        $data = $request->validate([
            'unit_id' => ['required', 'integer'], 'user_id' => ['required', 'integer'],
            'invitation_id' => ['nullable', 'integer'], 'expires_in_hours' => ['required', 'integer', 'between:1,720'],
        ]);
        $resend = ! empty($data['invitation_id']);
        abort_unless($permissions->allows($request->user(), 'unit-invitations.view', $building)
            && $permissions->allows($request->user(), $resend ? 'unit-invitations.update' : 'unit-invitations.create', $building), 403);

        // Each request sends exactly one user/unit pair; a failed recipient cannot undo other sends.
        return DB::transaction(function () use ($request, $data, $building, $service, $resend) {
            $unit = Unit::whereKey($data['unit_id'])->whereHas('floor.block', fn ($q) => $q->where('building_id', $building->id))->lockForUpdate()->firstOrFail();
            $pair = $this->pairs($building->id)->where('links.unit_id', $unit->id)->where('links.user_id', $data['user_id'])->first();
            abort_unless($pair, 403);
            if (! $pair->is_active || $pair->is_blocked || ! $pair->mobile) {
                throw ValidationException::withMessages(['recipient' => 'حساب فعال و شماره موبایل برای مخاطب لازم است.']);
            }
            $invitation = null;
            if ($resend) {
                $invitation = UnitInvitation::whereKey($data['invitation_id'])->where('unit_id', $unit->id)->where('invited_user_id', $data['user_id'])->lockForUpdate()->firstOrFail();
                if ($invitation->accepted_at || in_array($invitation->status, [InvitationStatus::Accepted, InvitationStatus::Cancelled], true)) {
                    throw ValidationException::withMessages(['invitation' => 'دعوت استفاده‌شده یا لغوشده قابل ارسال مجدد نیست.']);
                }
                if (UnitInvitation::where('unit_id', $unit->id)->where('invited_user_id', $data['user_id'])
                    ->where('id', '!=', $invitation->id)->whereIn('status', ['pending', 'sent'])
                    ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))->exists()) {
                    throw ValidationException::withMessages(['invitation' => 'دعوت فعال دیگری برای این کاربر و واحد وجود دارد؛ همان دعوت را ارسال مجدد کنید.']);
                }
                $invitation->update(['expires_at' => now()->addHours($data['expires_in_hours']), 'status' => InvitationStatus::Pending]);
            }
            try {
                $result = $resend ? $service->resend($invitation)
                    : $service->createForLinkedUsers($unit, $request->user(), collect([User::findOrFail($data['user_id'])]), $data)[0];

                return response()->json(['message' => 'ارسال به درگاه تأیید شد.', 'id' => $result['invitation']->id]);
            } catch (ValidationException $e) {
                throw $e;
            } catch (\Throwable $e) {
                $reference = (string) \Illuminate\Support\Str::uuid();
                \Illuminate\Support\Facades\Log::error('Invitation delivery failed', [
                    'reference' => $reference,
                    'building_id' => $building->id,
                    'invitation_id' => $invitation?->id,
                    'exception_class' => $e::class,
                ]);
                report($e);

                // Persist pending state/code rotation; never claim a failed/uncertain delivery was sent.
                return response()->json(['message' => 'ارسال تأیید نشد؛ کد پیگیری خطا: '.$reference], 502);
            }
        });
    }
}
