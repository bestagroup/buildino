<?php

namespace App\Console\Commands;

use App\Models\Building;
use App\Models\ChargePeriod;
use App\Models\Unit;
use App\Services\Charge\OccupancyRegistrationDate;
use App\Services\Charge\ResidentCountHistory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class AlignInitialOccupancies extends Command
{
    protected $signature = 'occupancies:align-registration-month {building : Building ID} {--apply : Persist changes; otherwise preview only}';

    protected $description = 'Align initial active occupancies and missing count history with their Persian registration month';

    public function handle(OccupancyRegistrationDate $dates, ResidentCountHistory $counts): int
    {
        try {
            $rows = DB::transaction(function () use ($dates, $counts): array {
                $building = Building::query()->lockForUpdate()->findOrFail($this->argument('building'));
                // Existing issued financial records must not silently acquire a different basis.
                if (ChargePeriod::where('building_id', $building->id)->whereIn('status', ['issued', 'closed'])->exists()) {
                    throw ValidationException::withMessages(['building' => 'این ساختمان دوره صادرشده یا بسته دارد؛ اصلاح سابقه نیازمند بررسی اختصاصی است.']);
                }
                $units = Unit::whereHas('floor.block', fn ($query) => $query->where('building_id', $building->id))
                    ->orderBy('id')->lockForUpdate()->get();
                $rows = [];
                $changed = false;
                foreach ($units as $unit) {
                    $relations = $unit->unitOccupancies()->withTrashed()->orderBy('id')->lockForUpdate()->get();
                    // Do not reinterpret tenant changes or ended historical relationships.
                    if ($relations->count() !== 1 || $relations[0]->trashed() || ! $relations[0]->is_active || $relations[0]->ends_at !== null) {
                        continue;
                    }
                    $occupancy = $relations[0];
                    $start = $dates->monthStart($occupancy->created_at);
                    $history = DB::table('unit_resident_counts')->where('unit_id', $unit->id)->orderBy('effective_from')->get();
                    // Use the first recorded count after registration, never today's later count.
                    $initial = $history->first(fn ($entry) => $entry->effective_from >= $occupancy->created_at->toDateString());
                    $existing = $history->firstWhere('effective_from', $start);
                    if (! $existing && ! $initial) {
                        throw ValidationException::withMessages(['resident_count' => 'واحد '.$unit->unit_number.' سابقه مستند تعداد نفرات ندارد.']);
                    }
                    $count = (int) ($initial?->resident_count ?? $existing->resident_count);
                    $rows[] = [$unit->unit_number, $occupancy->starts_at->toDateString(), $start, $count];
                    if ($this->option('apply')) {
                        if ($occupancy->starts_at->toDateString() !== $start) {
                            $occupancy->update(['starts_at' => $start]);
                            $changed = true;
                        }
                        if (! $existing || (int) $existing->resident_count !== $count) {
                            $counts->record($unit, $count, $start);
                            $changed = true;
                        }
                    }
                }

                if ($this->option('apply') && $changed) {
                    ChargePeriod::where('building_id', $building->id)->where('status', 'calculated')
                        ->update(['status' => 'draft', 'calculation_source_hash' => null]);
                }

                return $rows;
            });
            $this->table(['Unit', 'Previous start', 'Registration month start', 'Residents'], $rows);
            $this->info($this->option('apply') ? 'Applied.' : 'Preview only. Use --apply to persist.');

            return self::SUCCESS;
        } catch (ValidationException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
