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

final class BuildingWorkbookService
{
    public const HEADERS = ['ردیف', 'نام ساختمان', 'کد شناسایی ساختمان', 'شماره طبقه', 'شماره واحد', 'متراژ واحد', 'تعداد اتاق های واحد', 'تعداد پارکینگ واحد', 'نام مالک', 'نام خانوادگی مالک', 'شماره موبایل مالک', 'نام مستاجر در صورت وجود', 'نام خانوادگی مستاجر در صورت وجود', 'شماره موبایل مستاجر در صورت وجود', 'تعداد ساکن در واحد'];

    public const KEYS = ['row', 'building', 'code', 'floor', 'unit', 'area', 'bedrooms', 'parking_count', 'owner_first', 'owner_last', 'owner_mobile', 'tenant_first', 'tenant_last', 'tenant_mobile', 'resident_count'];

    public function read(string $path): array
    {
        return app(BuildingUserImportService::class)->read($path, self::HEADERS, self::KEYS);
    }

    public function validate(Building $building, User $actor, array $rows): array
    {
        foreach (['users.create', 'units.update', 'unit-ownerships.create', 'unit-occupancies.create'] as $permission) {
            abort_unless(app(PermissionChecker::class)->allows($actor, $permission, $building), 403);
        }
        $seen = [];
        $people = [];
        foreach ($rows as &$row) {
            $v = Validator::make($row, ['row' => 'required|integer|min:1', 'building' => 'required|string', 'code' => 'required|string', 'floor' => 'required|integer', 'unit' => 'required|string', 'area' => 'required|numeric|min:0.01|max:99999999', 'bedrooms' => 'required|integer|min:0|max:255', 'parking_count' => 'required|integer|min:0|max:1000', 'resident_count' => 'required|integer|min:0|max:1000', 'owner_first' => 'required|string|max:100', 'owner_last' => 'nullable|string|max:100', 'owner_mobile' => 'required|string', 'tenant_first' => 'nullable|string|max:100', 'tenant_last' => 'nullable|string|max:100', 'tenant_mobile' => 'nullable|string']);
            if ($v->fails()) {
                $this->fail('ردیف '.$row['row'].': '.implode(' ', $v->errors()->all()));
            }
            if ($row['building'] !== $building->title || $row['code'] !== $building->code) {
                $this->fail('نام یا کد ساختمان با ساختمان انتخابی مطابقت ندارد.');
            }
            $units = Unit::where('unit_number', $row['unit'])->whereHas('floor', fn ($q) => $q->where('floor_number', $row['floor'])->whereHas('block', fn ($q) => $q->where('building_id', $building->id)))->get();
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
            $tenant = $row['tenant_first'] !== '' || $row['tenant_last'] !== '' || $row['tenant_mobile'] !== '';
            if ($tenant && ((int) $row['resident_count'] === 0 || $row['tenant_first'] === '' || $row['tenant_mobile'] === '')) {
                $this->fail('مشخصات مستاجر و تعداد نفرات باید کامل باشد.');
            }
            foreach (['owner', ...($tenant ? ['tenant'] : [])] as $kind) {
                $mobile = $row[$kind.'_mobile'];
                if (preg_match('/^9[0-9]{9}$/', $mobile)) {
                    $mobile = '0'.$mobile;
                }
                $row[$kind.'_mobile'] = $mobile;
                $relation = $kind === 'tenant' ? 'tenant' : (! $tenant && (int) $row['resident_count'] > 0 ? 'owner_resident' : 'owner');
                $person = ['unit_id' => (string) $unit->id, 'unit_number' => (string) $unit->unit_number, 'first_name' => $row[$kind.'_first'], 'last_name' => $row[$kind.'_last'], 'mobile' => $mobile, 'relation' => $relation, 'resident_count' => $relation === 'owner' ? '' : $row['resident_count'], 'starts_at' => $row['starts_at'] ?? today()->toDateString()];
                $people[] = $person;
                $relations = $kind === 'owner' ? $unit->unitOwnerships() : $unit->unitOccupancies();
                if ($relations->where('is_active', true)->where(fn ($q) => $q->whereNull('ends_at')->orWhereDate('ends_at', '>=', today()))->whereHas('user', fn ($q) => $q->where('mobile', '!=', $mobile))->exists()) {
                    $this->fail('واحد '.$row['unit'].' ارتباط فعال متفاوت دارد؛ ابتدا در فرم مالکیت یا سکونت اصلاح کنید.');
                }
            }
            $occupantMobile = $tenant ? $row['tenant_mobile'] : ((int) $row['resident_count'] > 0 ? $row['owner_mobile'] : null);
            $occupancies = $unit->unitOccupancies()->where('is_active', true)->where(fn ($q) => $q->whereNull('ends_at')->orWhereDate('ends_at', '>=', today()));
            if ($occupancies->whereHas('user', fn ($q) => $occupantMobile ? $q->where('mobile', '!=', $occupantMobile) : $q)->exists()) {
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
            $plan = $this->validate($building, $actor, $rows);
            app(BuildingUserImportService::class)->commit($building, $actor, $plan['people']);
            foreach ($plan['rows'] as $row) {
                $unit = Unit::findOrFail($row['unit_id']);
                $unit->update(['area' => $row['area'], 'bedrooms' => $row['bedrooms'], 'parking_count' => $row['parking_count']]);
                app(ResidentCountHistory::class)->record($unit, (int) $row['resident_count'], $row['starts_at']);
            }
        });
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['file' => $message]);
    }
}
