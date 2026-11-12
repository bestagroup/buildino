<?php

namespace App\Services\Web;

use App\Models\Building;
use App\Models\Unit;
use App\Models\User;
use App\Services\Charge\ResidentCountHistory;
use App\Support\Authorization\PermissionChecker;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use OpenSpout\Reader\XLSX\Reader;

final class BuildingWorkbookService
{
    public const HEADERS = ['ردیف', 'نام ساختمان', 'کد شناسایی ساختمان', 'شماره طبقه', 'شماره واحد', 'متراژ واحد', 'تعداد اتاق های واحد', 'تعداد پارکینگ واحد', 'نام مالک', 'نام خانوادگی مالک', 'شماره موبایل مالک', 'نام مستاجر در صورت وجود', 'نام خانوادگی مستاجر در صورت وجود', 'شماره موبایل مستاجر در صورت وجود', 'تعداد ساکن در واحد'];

    public const KEYS = ['row', 'building', 'code', 'floor', 'unit', 'area', 'bedrooms', 'parking_count', 'owner_first', 'owner_last', 'owner_mobile', 'tenant_first', 'tenant_last', 'tenant_mobile', 'resident_count'];

    public function read(string $path): array
    {
        $rows = app(BuildingUserImportService::class)->read($path, self::HEADERS, self::KEYS, false);
        $reader = new Reader;
        $metadata = [];
        $hasMetadata = false;
        try {
            $reader->open($path);
            foreach ($reader->getSheetIterator() as $sheet) {
                if ($sheet->getName() !== '_buildino') {
                    continue;
                }
                $hasMetadata = true;
                foreach ($sheet->getRowIterator() as $record) {
                    $values = $record->toArray();
                    if (count($values) !== 2 || isset($metadata[(string) $values[0]]) || count($metadata) >= 500) {
                        $this->fail('اطلاعات داخلی فایل نامعتبر است.');
                    }
                    $metadata[(string) $values[0]] = (string) $values[1];
                }
            }
        } finally {
            $reader->close();
        }
        if ($hasMetadata) {
            foreach ($rows as &$row) {
                if (! isset($metadata[$row['row']])) {
                    $this->fail('شناسه ردیف در فایل یافت نشد؛ فایل جدید دریافت کنید.');
                }
                $row['_snapshot'] = $metadata[$row['row']];
            }
        }

        return $rows;
    }

    public function validate(Building $building, User $actor, array $rows): array
    {
        foreach (['users.create', 'units.update', 'unit-ownerships.create', 'unit-occupancies.create'] as $permission) {
            abort_unless(app(PermissionChecker::class)->allows($actor, $permission, $building), 403);
        }
        if (collect($rows)->contains(fn ($row) => isset($row['_snapshot']))) {
            return app(BuildingWorkbookSnapshot::class)->plan($building, $actor, $rows, $this);
        }
        $seen = [];
        $people = [];
        foreach ($rows as &$row) {
            $v = Validator::make($row, ['row' => 'required|integer|min:1', 'building' => 'required|string', 'code' => 'required|string', 'floor' => 'required|integer', 'unit' => 'required|string', 'area' => 'required|numeric|min:0.01|max:99999999', 'bedrooms' => 'required|integer|min:0|max:255', 'parking_count' => 'required|integer|min:0|max:1000', 'resident_count' => 'required|integer|min:0|max:1000', 'owner_first' => 'nullable|string|max:100', 'owner_last' => 'nullable|string|max:100', 'owner_mobile' => 'nullable|string', 'tenant_first' => 'nullable|string|max:100', 'tenant_last' => 'nullable|string|max:100', 'tenant_mobile' => 'nullable|string']);
            if ($v->fails()) {
                $this->fail('ردیف '.$row['row'].': '.implode(' ', $v->errors()->all()));
            }
            if ($row['building'] !== $building->title || $row['code'] !== $building->code) {
                $this->fail('نام یا کد ساختمان با ساختمان انتخابی مطابقت ندارد.');
            }
            $units = Unit::when(isset($row['unit_id']), fn ($q) => $q->whereKey($row['unit_id']))->where('unit_number', $row['unit'])->whereHas('floor', fn ($q) => $q->where('floor_number', $row['floor'])->whereHas('block', fn ($q) => $q->where('building_id', $building->id)))->get();
            if ($units->count() !== 1) {
                $this->fail('واحد ردیف '.$row['row'].' یافت نشد یا بین بلوک‌ها مبهم است.');
            }
            $unit = $units->first();
            if (isset($seen[$unit->id])) {
                $this->fail('هر واحد باید فقط یک ردیف داشته باشد.');
            }
            $seen[$unit->id] = true;
            if (isset($row['unit_id']) && (int) $row['unit_id'] !== $unit->id) {
                $this->fail('ساختار واحد پس از پیش‌نمایش تغییر کرده است.');
            }
            $row['unit_id'] = $unit->id;
            if ($row['owner_first'] === '' && $row['owner_mobile'] === '' && $row['owner_last'] === ''
                && $row['tenant_first'] === '' && $row['tenant_last'] === '' && $row['tenant_mobile'] === '' && (int) $row['resident_count'] === 0) {
                if ($unit->unitOwnerships()->where('is_active', true)->exists() || $unit->unitOccupancies()->where('is_active', true)->exists()) {
                    $this->fail('حذف ارتباط مالک یا ساکن از طریق خالی‌کردن فایل مجاز نیست.');
                }
                $row['starts_at'] ??= today()->toDateString();

                continue;
            }
            $tenant = $row['tenant_first'] !== '' || $row['tenant_last'] !== '' || $row['tenant_mobile'] !== '';
            if (! $tenant && $row['owner_first'] === '' && $row['owner_mobile'] === '' && (int) $row['resident_count'] > 0) {
                $this->fail('برای تعداد نفرات بیشتر از صفر، مشخصات مالک ساکن یا ساکن را تکمیل کنید.');
            }
            if ($tenant && (((int) $row['resident_count'] === 0 && empty($row['_occupant_id'])) || $row['tenant_first'] === '' || $row['tenant_mobile'] === '')) {
                $this->fail('مشخصات مستاجر و تعداد نفرات باید کامل باشد.');
            }
            foreach ([...(($row['owner_first'] !== '' || $row['owner_mobile'] !== '' || $row['owner_last'] !== '') ? ['owner'] : []), ...($tenant ? ['tenant'] : [])] as $kind) {
                $mobile = app(BuildingWorkbookSnapshot::class)->mobile($row[$kind.'_mobile']);
                if (preg_match('/^9[0-9]{9}$/', $mobile)) {
                    $mobile = '0'.$mobile;
                }
                $row[$kind.'_mobile'] = $mobile;
                $relation = $kind === 'tenant' ? 'tenant' : (! $tenant && (int) $row['resident_count'] > 0 ? 'owner_resident' : 'owner');
                $person = ['unit_id' => (string) $unit->id, 'unit_number' => (string) $unit->unit_number, 'first_name' => $row[$kind.'_first'], 'last_name' => $row[$kind.'_last'], 'mobile' => $mobile, 'relation' => $relation, 'resident_count' => $relation === 'owner' ? '' : $row['resident_count'], 'starts_at' => $row['starts_at'] ?? today()->toDateString()];
                $existingUser = app(BuildingUserImportService::class)->findUserByMobile($mobile);
                if (! $existingUser || ! in_array($existingUser->id, $row['_bound_users'] ?? [], true) || ($relation === 'owner_resident' && ! empty($row['_create_owner_occupancy']))) {
                    $people[] = $person;
                }
                $relations = $kind === 'owner' ? $unit->unitOwnerships() : $unit->unitOccupancies();
                if ($relations->where('is_active', true)->where(fn ($q) => $q->whereNull('ends_at')->orWhereDate('ends_at', '>=', today()))->where('user_id', '!=', $existingUser?->id ?? 0)->exists()) {
                    $this->fail('واحد '.$row['unit'].' ارتباط فعال متفاوت دارد؛ ابتدا در فرم مالکیت یا سکونت اصلاح کنید.');
                }
            }
            $occupantMobile = $tenant ? $row['tenant_mobile'] : ((int) $row['resident_count'] > 0 || ! empty($row['_occupant_id']) ? $row['owner_mobile'] : null);
            $occupant = $occupantMobile ? app(BuildingUserImportService::class)->findUserByMobile($occupantMobile) : null;
            $occupancies = $unit->unitOccupancies()->where('is_active', true)->where(fn ($q) => $q->whereNull('ends_at')->orWhereDate('ends_at', '>=', today()));
            if ($occupancies->where('user_id', '!=', $occupant?->id ?? 0)->exists()) {
                $this->fail('سکونت فعال واحد با فایل مطابقت ندارد.');
            }
            $row['starts_at'] ??= today()->toDateString();
        }
        app(BuildingUserImportService::class)->validate($building, $actor, $people);

        return ['rows' => $rows, 'people' => $people];
    }

    public function commit(Building $building, User $actor, array $rows): void
    {
        DB::transaction(function () use ($building, $actor, $rows) {
            Building::whereKey($building->id)->lockForUpdate()->firstOrFail();
            Unit::whereHas('floor.block', fn ($q) => $q->where('building_id', $building->id))->orderBy('id')->lockForUpdate()->get();
            $userIds = [];
            foreach ($rows as $row) {
                if (isset($row['_snapshot'])) {
                    $state = app(BuildingWorkbookSnapshot::class)->decode($row['_snapshot'])['state'];
                    $userIds = [...$userIds, $state['owner_id'], $state['tenant_id']];
                }
            }
            User::whereIn('id', array_filter($userIds))->orderBy('id')->lockForUpdate()->get();
            $plan = $this->validate($building, $actor, $rows);
            foreach ($plan['updates'] ?? [] as $id => $attributes) {
                $user = User::findOrFail($id);
                if (app(BuildingWorkbookSnapshot::class)->mobile($user->mobile ?? '') !== $attributes['mobile']) {
                    $user->mobile_verified_at = null;
                }
                $user->fill($attributes)->save();
            }
            app(BuildingUserImportService::class)->commit($building, $actor, $plan['people']);
            foreach ($plan['rows'] as $row) {
                $unit = Unit::findOrFail($row['unit_id']);
                $unit->update(['area' => $row['area'], 'bedrooms' => $row['bedrooms'], 'parking_count' => $row['parking_count']]);
                if ((int) $unit->resident_count !== (int) $row['resident_count']) {
                    app(ResidentCountHistory::class)->record($unit, (int) $row['resident_count'], $row['starts_at']);
                }
            }
        });
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['file' => $message]);
    }
}
