<?php

namespace App\Services\Web;

use App\Models\Building;
use App\Models\Unit;
use App\Models\User;
use App\Support\Authorization\PermissionChecker;
use App\Support\Input\DigitNormalizer;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\ValidationException;

/** Authenticated export identity and optimistic concurrency for editable workbooks. */
final class BuildingWorkbookSnapshot
{
    public function mobile(string $value): string
    {
        $value = app(DigitNormalizer::class)->normalize(trim($value));
        $value = preg_replace('/^(?:\+98|0098|98)(9[0-9]{9})$/', '0$1', $value);

        return preg_match('/^9[0-9]{9}$/', $value) ? '0'.$value : $value;
    }

    public function state(Unit $unit, Building $building): array
    {
        $active = fn ($q) => $q->where('is_active', true)->whereDate('starts_at', '<=', today())
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhereDate('ends_at', '>=', today()));
        $owners = $active($unit->unitOwnerships())->with('user')->get();
        $occupants = $active($unit->unitOccupancies())->with('user')->get();
        if ($owners->count() > 1 || $occupants->count() > 1) {
            $this->fail('واحد '.$unit->unit_number.' چند مالک یا ساکن فعال دارد؛ ویرایش آن با قالب تک‌ردیفی ممکن نیست.');
        }
        $owner = $owners->first()?->user;
        $resident = $occupants->first()?->user;
        if (($owners->isNotEmpty() && ! $owner) || ($occupants->isNotEmpty() && ! $resident)) {
            $this->fail('حساب مرتبط با واحد در دسترس نیست؛ ارتباط واحد را بررسی کنید.');
        }
        $tenant = $resident && $resident->id !== $owner?->id ? $resident : null;
        $parking = $unit->parking_count ?? $unit->unitParkingAssignments()
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhereDate('starts_at', '<=', today()))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhereDate('ends_at', '>=', today()))->count();
        $values = [$building->title, $building->code, (string) $unit->floor->floor_number, (string) $unit->unit_number,
            (string) $unit->area, (string) ($unit->bedrooms ?? 0), (string) $parking,
            $owner?->first_name ?? '', $owner?->last_name ?? '', $owner ? $this->mobile($owner->mobile ?? '') : '',
            $tenant?->first_name ?? '', $tenant?->last_name ?? '', $tenant ? $this->mobile($tenant->mobile ?? '') : '', (string) $unit->resident_count];

        return ['unit_id' => $unit->id, 'owner_id' => $owner?->id, 'tenant_id' => $tenant?->id, 'occupant_id' => $resident?->id,
            'values' => $values, 'relations' => [$owners->map->getAttributes()->all(), $occupants->map->getAttributes()->all()],
            'users' => collect([$owner, $tenant])->filter()->map(fn ($u) => [$u->id, $u->updated_at?->toISOString(), $u->mobile, $u->is_active, $u->is_blocked])->values()->all()];
    }

    public function token(array $state, Building $building, User $actor): string
    {
        return Crypt::encryptString(json_encode(['version' => 1, 'building_id' => $building->id, 'actor_id' => $actor->id, 'state' => $state], JSON_THROW_ON_ERROR));
    }

    public function decode(string $token): array
    {
        try {
            $data = json_decode(Crypt::decryptString($token), true, 512, JSON_THROW_ON_ERROR);
            if (($data['version'] ?? null) !== 1 || ! isset($data['state']['unit_id'], $data['building_id'], $data['actor_id'])) {
                $this->fail('شناسه داخلی فایل معتبر نیست.');
            }

            return $data;
        } catch (\Throwable $e) {
            $this->fail('شناسه داخلی فایل معتبر نیست؛ فایل را دوباره دانلود کنید.');
        }
    }

    public function plan(Building $building, User $actor, array $rows, BuildingWorkbookService $workbook): array
    {
        $legacy = $rows;
        $updates = $identities = $mobiles = [];
        $changed = [];
        foreach ($rows as $i => &$row) {
            $snapshot = $this->decode($row['_snapshot'] ?? '');
            abort_unless($snapshot['building_id'] === $building->id && $snapshot['actor_id'] === $actor->id, 403);
            $unit = Unit::whereKey($snapshot['state']['unit_id'])->whereHas('floor.block', fn ($q) => $q->where('building_id', $building->id))->firstOrFail();
            $current = $this->state($unit, $building);
            if ($current !== $snapshot['state']) {
                $this->fail('اطلاعات واحد '.$unit->unit_number.' پس از دانلود یا پیش‌نمایش تغییر کرده است؛ فایل جدید دریافت کنید.');
            }
            foreach (['building', 'code', 'floor', 'unit'] as $n => $key) {
                if ((string) $row[$key] !== $current['values'][$n]) {
                    $this->fail('مشخصات شناسایی ساختمان، طبقه و واحد قابل تغییر در این فایل نیست.');
                }
            }
            unset($legacy[$i]['_snapshot']);
            $legacy[$i]['unit_id'] = $unit->id;
            $legacy[$i]['_bound_users'] = array_filter([$current['owner_id'], $current['tenant_id']]);
            $legacy[$i]['_occupant_id'] = $current['occupant_id'];
            $legacy[$i]['_create_owner_occupancy'] = ! $current['occupant_id'] && (int) $current['values'][13] === 0 && (int) $row['resident_count'] > 0;
            foreach (['owner', 'tenant'] as $kind) {
                $row[$kind.'_mobile'] = $this->mobile($row[$kind.'_mobile']);
                $legacy[$i][$kind.'_mobile'] = $row[$kind.'_mobile'];
                $id = $current[$kind.'_id'];
                if (! $id) {
                    continue;
                }
                $user = User::findOrFail($id);
                $desired = ['first_name' => $row[$kind.'_first'], 'last_name' => $row[$kind.'_last'], 'mobile' => $row[$kind.'_mobile']];
                $validator = validator($desired, ['first_name' => 'required|string|max:100', 'last_name' => 'nullable|string|max:100', 'mobile' => ['required', 'regex:/^09[0-9]{9}$/']]);
                if ($validator->fails()) {
                    $this->fail('مشخصات '.$kind.' در ردیف '.$row['row'].' کامل یا معتبر نیست.');
                }
                if (isset($identities[$id]) && $identities[$id] !== $desired) {
                    $this->fail('اطلاعات یک کاربر در چند ردیف متفاوت است؛ تمام ردیف‌های آن کاربر را یکسان اصلاح کنید.');
                }
                if (isset($mobiles[$desired['mobile']]) && $mobiles[$desired['mobile']] !== $id) {
                    $this->fail('شماره موبایل یکسان برای دو کاربر مجاز نیست.');
                }
                $identities[$id] = $desired;
                $mobiles[$desired['mobile']] = $id;
                $existing = app(BuildingUserImportService::class)->findUserByMobile($desired['mobile']);
                if ($existing && $existing->id !== $id) {
                    $this->fail('شماره موبایل واردشده متعلق به حساب دیگری است.');
                }
                if ([$user->first_name, $user->last_name ?? '', $this->mobile($user->mobile ?? '')] !== array_values($desired)) {
                    abort_unless(app(PermissionChecker::class)->allows($actor, 'users.update', $building)
                        && app(ScopedUserManagementService::class)->canManage($actor, $user, 'users.update'), 403);
                    if (! $user->is_active || $user->is_blocked) {
                        $this->fail('حساب غیرفعال یا مسدود قابل ویرایش با اکسل نیست.');
                    }
                    $updates[$id] = $desired;
                }
                // The existing importer validates relationships against the unchanged identity.
                $legacy[$i][$kind.'_first'] = $user->first_name;
                $legacy[$i][$kind.'_last'] = $user->last_name ?? '';
                $legacy[$i][$kind.'_mobile'] = $this->mobile($user->mobile ?? '');
            }
        }
        unset($row);
        foreach ($rows as $i => $row) {
            $original = $this->decode($row['_snapshot'])['state']['values'];
            $values = array_map(fn ($key) => (string) $row[$key], array_slice(BuildingWorkbookService::KEYS, 1));
            foreach ($values as $column => $value) {
                $same = in_array($column, [2, 4, 5, 6, 13], true)
                    ? is_numeric($value) && (float) $value === (float) $original[$column]
                    : $value === $original[$column];
                if (! $same) {
                    $changed[$i] = $legacy[$i];
                    break;
                }
            }
        }
        $legacy = $changed;
        $plan = $workbook->validate($building, $actor, $legacy);
        foreach ($plan['people'] as &$person) {
            $user = app(BuildingUserImportService::class)->findUserByMobile($person['mobile']);
            if ($user && isset($updates[$user->id])) {
                $person = array_replace($person, $updates[$user->id]);
            }
        }
        unset($person);
        foreach ($plan['rows'] as $i => &$row) {
            foreach (['owner_first', 'owner_last', 'owner_mobile', 'tenant_first', 'tenant_last', 'tenant_mobile'] as $key) {
                $row[$key] = $rows[$i][$key];
            }
            $row['_snapshot'] = $rows[$i]['_snapshot'];
        }

        return $plan + ['updates' => $updates];
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['file' => $message]);
    }
}
