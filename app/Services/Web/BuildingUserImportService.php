<?php

namespace App\Services\Web;

use App\Models\Building;
use App\Models\ManagedUserScope;
use App\Models\Unit;
use App\Models\User;
use App\Services\OccupancyService;
use App\Services\UnitOwnershipService;
use App\Support\Authorization\PermissionChecker;
use App\Support\Input\DigitNormalizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use OpenSpout\Reader\XLSX\Reader;

final class BuildingUserImportService
{
    public const HEADERS = ['unit_id', 'unit_number', 'first_name', 'last_name', 'mobile', 'relation', 'resident_count', 'starts_at'];

    public function read(string $path, array $headers = self::HEADERS, ?array $keys = null, bool $skipEmptyPeople = true): array
    {
        $zip = new \ZipArchive;
        if ($zip->open($path) !== true) {
            $this->fail('فایل اکسل معتبر نیست.');
        }
        $size = 0;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $size += $zip->statIndex($i)['size'];
        }
        $zip->close();
        if ($size > 20 * 1024 * 1024) {
            $this->fail('حجم محتوای فایل بیش از حد مجاز است.');
        }
        $reader = new Reader;
        $rows = [];
        try {
            $reader->open($path);
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $index => $row) {
                    $values = array_map(function ($value) {
                        if ($value instanceof \DateTimeInterface) {
                            return $value->format('Y-m-d');
                        }
                        if (! is_scalar($value) && $value !== null) {
                            $this->fail('نوع سلول پشتیبانی نمی‌شود.');
                        }

                        return app(DigitNormalizer::class)->normalize(trim((string) $value));
                    }, $row->toArray());
                    if ($index === 1) {
                        if ($values !== $headers) {
                            $this->fail('ستون‌ها باید مطابق فایل نمونه باشند.');
                        }

                        continue;
                    }
                    if (count($rows) >= 500) {
                        $this->fail('حداکثر ۵۰۰ ردیف در هر فایل مجاز است.');
                    }
                    $values = array_pad($values, count($headers), '');
                    if (count($values) !== count($headers)) {
                        $this->fail("تعداد ستون‌های ردیف {$index} نادرست است.");
                    }
                    if ($keys === null && $values[2] === '' && $values[4] === '') {
                        continue;
                    }
                    if ($skipEmptyPeople && $keys !== null && count(array_filter(array_slice($values, 8, 6), fn ($v) => $v !== '')) === 0) {
                        continue;
                    }
                    $rows[] = array_combine($keys ?? $headers, $values);
                }
                break; // The template has exactly one data sheet.
            }
        } finally {
            $reader->close();
        }
        if (! $rows) {
            $this->fail('فایل ردیف قابل ثبت ندارد.');
        }

        return $rows;
    }

    public function validate(Building $building, User $actor, array $rows): array
    {
        $permissions = app(PermissionChecker::class);
        abort_unless($permissions->allows($actor, 'users.create', $building), 403);
        $seen = $identities = $counts = [];
        foreach ($rows as $index => &$row) {
            if (preg_match('/^9[0-9]{9}$/', $row['mobile'])) {
                $row['mobile'] = '0'.$row['mobile'];
            }
            $row['relation'] = ['مالک' => 'owner', 'ساکن' => 'resident', 'مستاجر' => 'tenant', 'مالک ساکن' => 'owner_resident'][$row['relation']] ?? $row['relation'];
            $v = Validator::make($row, [
                'unit_id' => 'required|integer|min:1', 'unit_number' => 'required|string|max:100',
                'first_name' => 'required|string|max:100', 'last_name' => 'nullable|string|max:100',
                'mobile' => ['required', 'regex:/^09[0-9]{9}$/'],
                'relation' => 'required|in:owner,resident,tenant,owner_resident',
                'starts_at' => 'required|date_format:Y-m-d',
                'resident_count' => 'nullable|integer|min:1|max:1000',
            ]);
            if ($v->fails()) {
                $this->fail('ردیف '.($index + 2).': '.implode(' ', $v->errors()->all()));
            }
            $unit = Unit::whereKey($row['unit_id'])->whereHas('floor.block', fn ($q) => $q->where('building_id', $building->id))->first();
            if (! $unit || (string) $unit->unit_number !== $row['unit_number']) {
                $this->fail('واحد ردیف '.($index + 2).' متعلق به ساختمان انتخابی نیست یا شماره آن تغییر کرده است.');
            }
            $owner = in_array($row['relation'], ['owner', 'owner_resident'], true);
            $resident = $row['relation'] !== 'owner';
            foreach (array_filter([$owner ? 'unit-ownerships.create' : null, $resident ? 'unit-occupancies.create' : null]) as $permission) {
                abort_unless($permissions->allows($actor, $permission, $building), 403);
            }
            if ($resident && $row['resident_count'] === '') {
                $this->fail('تعداد نفرات برای سکونت الزامی است.');
            }
            $key = $row['unit_id'].':'.$row['mobile'];
            if (isset($seen[$key])) {
                $this->fail('ردیف تکراری برای کاربر و واحد؛ برای مالک ساکن از owner_resident استفاده کنید.');
            }
            $seen[$key] = true;
            $identity = [$row['first_name'], $row['last_name']];
            if (isset($identities[$row['mobile']]) && $identities[$row['mobile']] !== $identity) {
                $this->fail('نام‌های متفاوت برای یک شماره موبایل ثبت شده است.');
            }
            $identities[$row['mobile']] = $identity;
            $user = $this->findUserByMobile($row['mobile']);
            if ($user && ($user->trashed() || ! $user->is_active || $user->is_blocked || [$user->first_name, $user->last_name ?? ''] !== $identity)) {
                $this->fail('اطلاعات یکی از شماره‌ها با حساب موجود قابل تطبیق نیست؛ ثبت دستی بررسی شود.');
            }
            if ($resident) {
                $countKey = $row['unit_id'].':'.$row['starts_at'];
                if (isset($counts[$countKey]) && $counts[$countKey] !== $row['resident_count']) {
                    $this->fail('تعداد نفرات واحد در یک تاریخ باید یکسان باشد.');
                }
                $counts[$countKey] = $row['resident_count'];
            }
        }

        return $rows;
    }

    public function commit(Building $building, User $actor, array $rows): void
    {
        DB::transaction(function () use ($building, $actor, $rows) {
            Building::whereKey($building->id)->lockForUpdate()->firstOrFail();
            $rows = $this->validate($building, $actor, $rows);
            Unit::whereIn('id', array_column($rows, 'unit_id'))->orderBy('id')->lockForUpdate()->get();
            foreach ($rows as $row) {
                $user = $this->findUserByMobile($row['mobile']);
                if (! $user) {
                    $user = User::create(['first_name' => $row['first_name'], 'last_name' => $row['last_name'], 'mobile' => $row['mobile'], 'password' => Str::random(48), 'must_change_password' => true, 'is_active' => true, 'is_blocked' => false]);
                    ManagedUserScope::firstOrCreate(['user_id' => $user->id, 'scope_type' => $building->getMorphClass(), 'scope_id' => $building->id], ['assigned_by' => $actor->id]);
                }
                $unit = Unit::findOrFail($row['unit_id']);
                $data = ['unit_id' => $unit->id, 'user_id' => $user->id, 'starts_at' => $row['starts_at'], 'is_active' => true, 'is_primary' => false];
                if (in_array($row['relation'], ['owner', 'owner_resident'], true)) {
                    if (! $unit->unitOwnerships()->where('user_id', $user->id)->where('is_active', true)->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', today()))->exists()) {
                        app(UnitOwnershipService::class)->assign($data, $actor);
                    }
                }
                if ($row['relation'] !== 'owner') {
                    if (! $unit->unitOccupancies()->where('user_id', $user->id)->where('is_active', true)->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', today()))->exists()) {
                        app(OccupancyService::class)->assign($data + ['occupancy_type' => $row['relation'] === 'owner_resident' ? 'owner' : $row['relation'], 'resident_count' => (int) $row['resident_count']], $actor);
                    }
                }
            }
        });
    }

    public function findUserByMobile(string $mobile): ?User
    {
        $national = substr($mobile, 1);
        $users = User::withTrashed()->whereIn('mobile', [
            $mobile, $national, '+98'.$national, '0098'.$national, '98'.$national,
        ])->get();
        if ($users->count() > 1) {
            $this->fail('برای یک شماره موبایل چند حساب موجود است؛ ابتدا حساب‌های تکراری را بررسی کنید.');
        }

        return $users->first();
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['file' => $message]);
    }
}
