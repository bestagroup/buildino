<?php

namespace Tests\Feature\Web;

use App\Models\Permission;
use App\Models\Unit;
use App\Models\UnitOwnership;
use App\Models\User;
use App\Models\Wallet;
use App\Services\OccupancyService;
use App\Services\Web\BuildingUserImportService;
use App\Services\Web\BuildingWorkbookService;
use Database\Seeders\AccessScenarioSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\XLSX\Reader;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class BuildingUserImportTest extends TestCase
{
    use RefreshDatabase;

    private function scenario(): array
    {
        $this->seed(AccessScenarioSeeder::class);
        $manager = User::where('email', 'role.building@buildino.local')->firstOrFail();
        $owner = User::where('email', 'role.owner@buildino.local')->firstOrFail();
        $unit = UnitOwnership::where('user_id', $owner->id)->firstOrFail()->unit;
        $unit->unitOwnerships()->delete();
        $unit->unitOccupancies()->delete();
        $this->actingAs($manager);

        return [$manager, $unit, $unit->floor->block->building];
    }

    private function workbookRow(Unit $unit, string $mobile, string $name): array
    {
        $building = $unit->floor->block->building;

        return ['1', $building->title, $building->code, (string) $unit->floor->floor_number, (string) $unit->unit_number, '120', '3', '2', '1', 'مسکونی', $name, 'اکسل', $mobile, '', '', '', '3'];
    }

    private function file(array $rows): UploadedFile
    {
        $building = \App\Models\Building::where('code', $rows[0][2])->firstOrFail();
        $exported = $this->exportedRows($building);
        foreach ($rows as $values) {
            foreach ($exported as &$record) {
                if ($record['unit'] === $values[4] && $record['floor'] === $values[3]) {
                    $record = array_replace($record, array_combine(BuildingWorkbookService::KEYS, $values));
                }
            }
            unset($record);
        }

        return $this->signedFile($exported);
    }

    private function signedFile(array $rows, ?array $dataRows = null, ?array $headers = null): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'import-test');
        $writer = new Writer;
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues($headers ?? BuildingWorkbookService::HEADERS));
        foreach ($dataRows ?? $rows as $row) {
            $writer->addRow(Row::fromValues(array_map(fn ($key) => $row[$key], BuildingWorkbookService::KEYS)));
        }
        $writer->addNewSheetAndMakeItCurrent()->setName('_buildino')->setIsVisible(false);
        foreach ($rows as $index => $row) {
            $writer->addRow(Row::fromValues([(string) ($index + 1), $row['_snapshot']]));
        }
        $writer->close();
        $file = UploadedFile::fake()->createWithContent('users.xlsx', file_get_contents($path));
        unlink($path);

        return $file;
    }

    public function test_preview_does_not_write_and_commit_creates_user_relationships_and_wallet(): void
    {
        [$manager, $unit, $building] = $this->scenario();
        $this->get('/management/operations/users')->assertOk()->assertInertia(fn ($page) => $page->where('workspace.navigation', fn ($items) => collect($items)->contains('url', route('management.user-import.index'))));
        $this->get(route('management.user-import.index'))->assertOk()->assertSee('دانلود فایل نمونه');
        $this->get(route('management.user-import.template', $building))->assertOk();
        $file = $this->file([$this->workbookRow($unit, '09129998877', 'آزمایش')]);
        $this->post(route('management.user-import.preview', $building), ['file' => $file])->assertOk()->assertSee('تأیید و ثبت');
        $this->assertDatabaseMissing('users', ['mobile' => '09129998877']);
        $token = session('building_user_import.token');
        $this->post(route('management.user-import.commit', $building), ['token' => $token])->assertRedirect();
        $user = User::where('mobile', '09129998877')->firstOrFail();
        $this->assertTrue($user->wallets()->exists());
        $this->assertDatabaseHas('units', ['id' => $unit->id, 'area' => 120, 'bedrooms' => 3, 'parking_count' => 2, 'resident_count' => 3]);
        $this->assertDatabaseHas('unit_ownerships', ['unit_id' => $unit->id, 'user_id' => $user->id]);
        $this->assertDatabaseHas('unit_occupancies', ['unit_id' => $unit->id, 'user_id' => $user->id]);
        $this->post(route('management.user-import.commit', $building), ['token' => $token])->assertStatus(419);
    }

    public function test_foreign_units_and_mismatched_existing_accounts_are_rejected(): void
    {
        [$manager, $unit, $building] = $this->scenario();
        $foreign = Unit::whereHas('floor.block', fn ($q) => $q->where('building_id', '!=', $building->id))->firstOrFail();
        foreach ([[$foreign, '09129998877', 'جدید'], [$unit, $manager->mobile, 'نام نادرست']] as [$target, $mobile, $name]) {
            if ($target->id === $foreign->id) {
                $this->get(route('management.user-import.template', $target->floor->block->building))->assertForbidden();
                continue;
            }
            $file = $this->file([$this->workbookRow($target, $mobile, $name)]);
            $this->post(route('management.user-import.preview', $building), ['file' => $file])->assertSessionHasErrors('file');
            $this->assertNull(session('building_user_import'));
        }
        $this->assertDatabaseMissing('users', ['mobile' => '09129998877']);
    }

    public function test_invalid_later_row_rolls_back_and_preview_permissions_are_checked_again(): void
    {
        [$manager, $unit, $building] = $this->scenario();
        $rows = [array_combine(BuildingUserImportService::HEADERS, [(string) $unit->id, (string) $unit->unit_number, 'جدید', 'کاربر', '09129998877', 'owner', '', today()->toDateString()])];
        $bad = $rows[0];
        $bad['mobile'] = 'not-a-phone';
        try {
            app(BuildingUserImportService::class)->commit($building, $manager, [$rows[0], $bad]);
            $this->fail('Invalid row accepted');
        } catch (ValidationException) {
            $this->assertDatabaseMissing('users', ['mobile' => '09129998877']);
        }
        $this->post(route('management.user-import.preview', $building), ['file' => $this->file([$this->workbookRow($unit, '09129998877', 'جدید')])])->assertOk();
        $token = session('building_user_import.token');
        $this->actingAs(User::factory()->create(['is_active' => true, 'is_blocked' => false]));
        $this->postJson(route('management.user-import.commit', $building), ['token' => $token])->assertRedirect(route('login'))->assertSessionHasErrors('login');
        $this->assertDatabaseMissing('users', ['mobile' => '09129998877']);
    }

    public function test_failure_after_user_creation_rolls_back_personal_wallet_and_relationships(): void
    {
        [$manager, $unit, $building] = $this->scenario();
        $rows = [array_combine(BuildingUserImportService::HEADERS, [(string) $unit->id, (string) $unit->unit_number, 'جدید', 'کاربر', '09129998877', 'owner_resident', '2', today()->toDateString()])];
        $walletCount = Wallet::count();
        $this->mock(OccupancyService::class, fn ($mock) => $mock->shouldReceive('assign')->once()->andThrow(new \RuntimeException('failed')));
        try {
            app(BuildingUserImportService::class)->commit($building, $manager, $rows);
            $this->fail('Expected failure');
        } catch (\RuntimeException $e) {
            $this->assertSame('failed', $e->getMessage());
        }
        $this->assertDatabaseMissing('users', ['mobile' => '09129998877']);
        $this->assertSame($walletCount, Wallet::count());
    }

    public function test_operations_card_and_multiple_building_selection(): void
    {
        $this->scenario();
        $this->get('/management/operations')->assertOk()->assertSee('ورود اطلاعات کاربران از طریق فایل اکسل');
        $this->get('/management/operations/user-import')->assertOk()->assertDontSee('id="import-building"', false);
        $this->actingAs(User::where('email', 'role.superadmin@buildino.local')->firstOrFail());
        $this->get('/management/operations/user-import')->assertOk()->assertSee('id="import-building"', false)->assertDontSee('دانلود فایل نمونه');
    }

    public function test_template_has_exact_seventeen_headers_and_structure(): void
    {
        [, $unit, $building] = $this->scenario();
        $response = $this->get(route('management.user-import.template', $building))->assertOk();
        $reader = new Reader;
        $path = $response->baseResponse->getFile()->getPathname();
        $reader->open($path);
        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $index => $row) {
                if ($index === 1) {
                    $this->assertSame(BuildingWorkbookService::HEADERS, $row->toArray());
                } else {
                    $this->assertCount(17, $row->toArray());
                    $this->assertSame($building->code, $row->toArray()[2]);
                }
            }
            break;
        }
        $reader->close();
        unlink($path);
    }

    public function test_tenant_and_owner_are_separate_and_repeat_import_is_idempotent(): void
    {
        [$manager, $unit, $building] = $this->scenario();
        $values = $this->workbookRow($unit, '09129998877', 'مالک');
        $values[13] = 'مستاجر';
        $values[14] = 'اکسل';
        $values[15] = '09129998878';
        $rows = [array_combine(BuildingWorkbookService::KEYS, $values)];
        app(BuildingWorkbookService::class)->commit($building, $manager, $rows);
        app(BuildingWorkbookService::class)->commit($building, $manager, $rows);
        $owner = User::where('mobile', '09129998877')->firstOrFail();
        $tenant = User::where('mobile', '09129998878')->firstOrFail();
        $this->assertSame(1, $unit->unitOwnerships()->count());
        $this->assertSame(1, $unit->unitOccupancies()->count());
        $this->assertDatabaseHas('unit_ownerships', ['unit_id' => $unit->id, 'user_id' => $owner->id]);
        $this->assertDatabaseHas('unit_occupancies', ['unit_id' => $unit->id, 'user_id' => $tenant->id, 'occupancy_type' => 'tenant']);
    }

    public function test_existing_owner_resident_with_international_mobile_is_not_a_conflict(): void
    {
        [$manager, $unit, $building] = $this->scenario();
        $rows = [array_combine(BuildingWorkbookService::KEYS, $this->workbookRow($unit, '09129998877', 'مالک'))];
        $service = app(BuildingWorkbookService::class);
        $service->commit($building, $manager, $rows);
        $owner = User::where('mobile', '09129998877')->firstOrFail();
        $owner->update(['mobile' => '+989129998877']);

        $service->commit($building, $manager, $rows);

        $this->assertSame(1, $unit->unitOwnerships()->count());
        $this->assertSame(1, $unit->unitOccupancies()->count());
        $this->assertDatabaseMissing('users', ['mobile' => '09129998877']);
        $this->assertDatabaseHas('unit_occupancies', ['unit_id' => $unit->id, 'user_id' => $owner->id]);
    }

    private function exportedRows($building): array
    {
        $response = $this->get(route('management.user-import.template', $building))->assertOk();
        $path = $response->baseResponse->getFile()->getPathname();
        try {
            return app(BuildingWorkbookService::class)->read($path);
        } finally {
            unlink($path);
        }
    }

    private function populatedWorkbook(): array
    {
        [$manager, $unit, $building] = $this->scenario();
        app(BuildingWorkbookService::class)->commit($building, $manager, [array_combine(BuildingWorkbookService::KEYS, $this->workbookRow($unit, '09129998877', 'مالک'))]);
        $user = User::where('mobile', '09129998877')->firstOrFail();
        $rows = $this->exportedRows($building);
        $index = array_search((string) $unit->unit_number, array_column($rows, 'unit'), true);

        return [$manager, $unit, $building, $user, $rows, $index];
    }

    public function test_export_round_trip_updates_the_same_account_and_preserves_wallet_and_relationships(): void
    {
        [$manager, $unit, $building, $user, $rows, $index] = $this->populatedWorkbook();
        $user->forceFill(['mobile_verified_at' => now()])->save();
        $rows = $this->exportedRows($building);
        $wallet = Wallet::where('owner_type', $user->getMorphClass())->where('owner_id', $user->id)->firstOrFail();
        $rows[$index]['owner_first'] = 'اصلاح';
        $rows[$index]['owner_last'] = 'جدید';
        $rows[$index]['owner_mobile'] = '۰۹۱۲۳۴۵۶۷۸۹';
        $rows[$index]['area'] = '135.50';
        $rows[$index]['resident_count'] = '4';
        $service = app(BuildingWorkbookService::class);
        $plan = $service->validate($building, $manager, $rows);
        $this->assertSame('مالک', $user->fresh()->first_name);
        $this->assertCount(1, $plan['rows']);
        $service->commit($building, $manager, $plan['rows']);
        $this->assertSame('اصلاح', $user->fresh()->first_name);
        $this->assertSame('09123456789', $user->fresh()->mobile);
        $this->assertNull($user->fresh()->mobile_verified_at);
        $this->assertSame(4, $unit->fresh()->resident_count);
        $this->assertEquals(135.50, $unit->fresh()->area);
        $this->assertDatabaseHas('wallets', ['id' => $wallet->id, 'owner_id' => $user->id]);
        $this->assertSame(1, $unit->unitOwnerships()->count());
        $this->assertSame(1, $unit->unitOccupancies()->count());
    }

    public function test_unchanged_export_is_a_noop_and_contains_existing_names_and_text_mobile(): void
    {
        [$manager, $unit, $building, $user, $rows, $index] = $this->populatedWorkbook();
        $this->assertSame('مالک', $rows[$index]['owner_first']);
        $this->assertSame('09129998877', $rows[$index]['owner_mobile']);
        $this->assertSame([], app(BuildingWorkbookService::class)->validate($building, $manager, $rows)['rows']);
        $response = $this->get(route('management.user-import.template', $building))->assertOk();
        $path = $response->baseResponse->getFile()->getPathname();
        $zip = new \ZipArchive;
        $zip->open($path);
        $this->assertStringContainsString('B Nazanin', $zip->getFromName('xl/styles.xml'));
        $this->assertStringContainsString('24476B', $zip->getFromName('xl/styles.xml'));
        $this->assertStringContainsString('rightToLeft="true"', $zip->getFromName('xl/worksheets/sheet1.xml'));
        $this->assertStringContainsString('state="hidden"', $zip->getFromName('xl/workbook.xml'));
        $this->assertMatchesRegularExpression('/<c[^>]*r="M[0-9]+"[^>]*t="inlineStr"/', $zip->getFromName('xl/worksheets/sheet1.xml'));
        $zip->close();
        unlink($path);
    }

    public function test_stale_workbook_cannot_overwrite_changes_after_preview(): void
    {
        [$manager, $unit, $building, $user, $rows, $index] = $this->populatedWorkbook();
        $rows[$index]['owner_first'] = 'اکسل';
        $service = app(BuildingWorkbookService::class);
        $plan = $service->validate($building, $manager, $rows);
        $user->update(['first_name' => 'ویرایش جدیدتر']);
        try {
            $service->commit($building, $manager, $plan['rows']);
            $this->fail('Stale workbook accepted');
        } catch (ValidationException $e) {
            $this->assertSame('ویرایش جدیدتر', $user->fresh()->first_name);
        }
    }

    public function test_duplicate_mobile_does_not_partially_update_user_or_unit(): void
    {
        [$manager, $unit, $building, $user, $rows, $index] = $this->populatedWorkbook();
        User::factory()->create(['mobile' => '+989123456789']);
        $rows[$index]['owner_mobile'] = '09123456789';
        $rows[$index]['area'] = '155';
        try {
            app(BuildingWorkbookService::class)->commit($building, $manager, $rows);
            $this->fail('Duplicate mobile accepted');
        } catch (ValidationException $e) {
            $this->assertSame('09129998877', $user->fresh()->mobile);
            $this->assertEquals(120, $unit->fresh()->area);
        }
    }

    public function test_signed_upload_preview_and_confirmation_persist_profile_changes(): void
    {
        [$manager, $unit, $building, $user, $rows, $index] = $this->populatedWorkbook();
        $rows[$index]['owner_last'] = 'ویرایش اکسل';
        $path = tempnam(sys_get_temp_dir(), 'roundtrip-');
        $writer = new Writer;
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues(BuildingWorkbookService::HEADERS));
        foreach ($rows as $row) {
            $writer->addRow(Row::fromValues(array_map(fn ($key) => $row[$key], BuildingWorkbookService::KEYS)));
        }
        $writer->addNewSheetAndMakeItCurrent()->setName('_buildino')->setIsVisible(false);
        foreach ($rows as $row) {
            $writer->addRow(Row::fromValues([$row['row'], $row['_snapshot']]));
        }
        $writer->close();
        $file = UploadedFile::fake()->createWithContent('edited.xlsx', file_get_contents($path));
        unlink($path);
        $this->post(route('management.user-import.preview', $building), ['file' => $file])->assertOk()->assertSee('ویرایش اکسل');
        $this->assertSame('اکسل', $user->fresh()->last_name);
        $token = session('building_user_import.token');
        $this->post(route('management.user-import.commit', $building), ['token' => $token])->assertRedirect();
        $this->assertSame('ویرایش اکسل', $user->fresh()->last_name);
        $this->post(route('management.user-import.commit', $building), ['token' => $token])->assertStatus(419);
    }

    public function test_profile_edit_requires_update_permission_even_with_valid_export(): void
    {
        [$manager, $unit, $building, $user, $rows, $index] = $this->populatedWorkbook();
        $rows[$index]['owner_first'] = 'ممنوع';
        $permission = Permission::where('name', 'users.update')->firstOrFail();
        foreach ($manager->userRoleAssignments()->with('role')->get() as $assignment) {
            $assignment->role->permissions()->detach($permission->id);
        }
        try {
            app(BuildingWorkbookService::class)->commit($building, $manager, $rows);
            $this->fail('Missing update permission accepted');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
            $this->assertSame('مالک', $user->fresh()->first_name);
        }
    }

    public function test_workbook_export_is_bound_to_the_downloading_actor(): void
    {
        [$manager, $unit, $building, $user, $rows, $index] = $this->populatedWorkbook();
        $rows[$index]['owner_first'] = 'غیرمجاز';
        $admin = User::where('email', 'role.superadmin@buildino.local')->firstOrFail();
        try {
            app(BuildingWorkbookService::class)->commit($building, $admin, $rows);
            $this->fail('Another actor export accepted');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
    }

    public function test_existing_tenant_without_owner_can_correct_profile_without_recreating_occupancy(): void
    {
        [$manager, $unit, $building, $user, $rows, $index] = $this->populatedWorkbook();
        $unit->unitOwnerships()->delete();
        $rows = $this->exportedRows($building);
        $rows[$index]['tenant_last'] = 'اصلاح ساکن';
        app(BuildingWorkbookService::class)->commit($building, $manager, $rows);
        $this->assertSame('اصلاح ساکن', $user->fresh()->last_name);
        $this->assertSame(0, $unit->unitOwnerships()->count());
        $this->assertSame(1, $unit->unitOccupancies()->count());
    }

    public function test_resident_count_zero_is_saved_without_deleting_existing_relationships(): void
    {
        [$manager, $unit, $building, $user, $rows, $index] = $this->populatedWorkbook();
        $rows[$index]['resident_count'] = '0';
        app(BuildingWorkbookService::class)->commit($building, $manager, $rows);
        $this->assertSame(0, $unit->fresh()->resident_count);
        $this->assertSame(1, $unit->unitOccupancies()->where('is_active', true)->count());
        $this->assertDatabaseHas('unit_resident_counts', ['unit_id' => $unit->id, 'resident_count' => 0]);
    }

    public function test_vacant_owner_unit_can_gain_owner_occupancy_through_count_change(): void
    {
        [$manager, $unit, $building] = $this->scenario();
        $values = $this->workbookRow($unit, '09129998877', 'مالک');
        $values[16] = '0';
        $service = app(BuildingWorkbookService::class);
        $service->commit($building, $manager, [array_combine(BuildingWorkbookService::KEYS, $values)]);
        $rows = $this->exportedRows($building);
        foreach ($rows as &$row) {
            if ($row['unit'] === (string) $unit->unit_number) {
                $row['resident_count'] = '3';
            }
        }
        unset($row);
        $service->commit($building, $manager, $rows);
        $this->assertSame(3, $unit->fresh()->resident_count);
        $this->assertSame(1, $unit->unitOccupancies()->count());
    }

    public function test_positive_resident_count_requires_a_person_for_new_empty_unit(): void
    {
        [$manager, $unit, $building] = $this->scenario();
        $values = $this->workbookRow($unit, '', '');
        $values[11] = '';
        $this->expectException(ValidationException::class);
        app(BuildingWorkbookService::class)->commit($building, $manager, [array_combine(BuildingWorkbookService::KEYS, $values)]);
    }

    public function test_tampered_snapshot_is_rejected(): void
    {
        [$manager, $unit, $building, $user, $rows, $index] = $this->populatedWorkbook();
        $rows[$index]['_snapshot'] .= 'tampered';
        $this->expectException(ValidationException::class);
        app(BuildingWorkbookService::class)->validate($building, $manager, $rows);
    }

    public function test_conflicting_or_incomplete_tenant_is_not_written(): void
    {
        [$manager, $unit, $building] = $this->scenario();
        $values = $this->workbookRow($unit, '09129998877', 'مالک');
        $values[13] = 'ناقص';
        $this->post(route('management.user-import.preview', $building), ['file' => $this->file([$values])])->assertSessionHasErrors('file');
        $values[13] = '';
        $fileBeforeOwnership = $this->file([$values]);
        $existing = User::factory()->create();
        UnitOwnership::create(['unit_id' => $unit->id, 'user_id' => $existing->id, 'is_active' => true, 'starts_at' => today()]);
        $this->post(route('management.user-import.preview', $building), ['file' => $fileBeforeOwnership])->assertSessionHasErrors('file');
        $this->assertDatabaseMissing('users', ['mobile' => '09129998877']);
    }

    public function test_number_storage_and_usage_edit_preserves_unit_identity_and_ignores_locked_cells(): void
    {
        [$manager, $unit, $building, $user, $rows, $index] = $this->populatedWorkbook();
        $ownershipId = $unit->unitOwnerships()->firstOrFail()->id;
        $occupancyId = $unit->unitOccupancies()->firstOrFail()->id;
        $rows[$index] = array_replace($rows[$index], [
            'row' => '999', 'building' => 'تغییر غیرمجاز', 'code' => 'BAD', 'floor' => '999',
            'unit' => '001-A', 'storage_count' => '2', 'usage_type' => 'اداری', 'area' => '145',
        ]);
        $file = $this->signedFile($rows);
        $this->post(route('management.user-import.preview', $building), ['file' => $file])->assertOk();
        $this->post(route('management.user-import.commit', $building), ['token' => session('building_user_import.token')])->assertRedirect();
        $this->assertDatabaseHas('units', ['id' => $unit->id, 'floor_id' => $unit->floor_id,
            'unit_number' => '001-A', 'storage_count' => 2, 'usage_type' => 'office', 'area' => 145]);
        $this->assertSame($building->title, $building->fresh()->title);
        $this->assertSame($building->code, $building->fresh()->code);
        $this->assertSame($ownershipId, $unit->unitOwnerships()->first()->id);
        $this->assertSame($occupancyId, $unit->unitOccupancies()->first()->id);
        $this->assertSame($user->id, $unit->unitOwnerships()->first()->user_id);
    }

    public function test_added_rows_columns_or_removed_metadata_cannot_be_imported(): void
    {
        [$manager, $unit, $building, $user, $rows, $index] = $this->populatedWorkbook();
        $rows[$index]['area'] = '180';
        $extra = [...$rows, $rows[$index]];
        $files = [
            $this->signedFile($rows, $extra),
            $this->signedFile($rows, null, [...BuildingWorkbookService::HEADERS, 'ستون اضافه']),
            $this->signedFile($extra), // Even copied hidden metadata cannot extend the authenticated row count.
        ];
        $unsigned = $this->signedFile($rows);
        $zip = new \ZipArchive;
        $zip->open($unsigned->getRealPath());
        $zip->deleteName('xl/worksheets/sheet2.xml');
        $zip->close();
        $files[] = $unsigned;
        foreach ($files as $file) {
            $this->post(route('management.user-import.preview', $building), ['file' => $file])->assertSessionHasErrors('file');
            $this->assertNull(session('building_user_import'));
            $this->assertNotEquals(180, $unit->fresh()->area);
        }
    }

    public function test_duplicate_unit_number_and_invalid_usage_are_rejected_atomically(): void
    {
        [$manager, $unit, $building, $user, $rows, $index] = $this->populatedWorkbook();
        $other = Unit::create(['floor_id' => $unit->floor_id, 'unit_number' => 'TAKEN', 'area' => 90, 'usage_type' => 'residential']);
        foreach ([['unit' => 'TAKEN'], ['usage_type' => 'invalid'], ['storage_count' => '-1']] as $invalid) {
            $candidate = $rows;
            $candidate[$index] = array_replace($candidate[$index], $invalid, ['owner_first' => 'نباید ثبت شود']);
            try {
                app(BuildingWorkbookService::class)->commit($building, $manager, $candidate);
                $this->fail('Invalid changes accepted');
            } catch (ValidationException) {
                $this->assertSame('مالک', $user->fresh()->first_name);
                $this->assertSame($unit->unit_number, $unit->fresh()->unit_number);
            }
        }
    }

    public function test_locked_cell_only_edits_are_a_noop_and_number_changes_after_preview_are_rejected(): void
    {
        [$manager, $unit, $building, $user, $rows, $index] = $this->populatedWorkbook();
        $rows[$index] = array_replace($rows[$index], ['row' => '999', 'building' => 'ignored', 'code' => 'ignored', 'floor' => '999']);
        $service = app(BuildingWorkbookService::class);
        $this->assertSame([], $service->validate($building, $manager, $rows)['rows']);
        $rows[$index]['unit'] = 'NEW-1';
        $plan = $service->validate($building, $manager, $rows);
        $unit->update(['unit_number' => 'CONCURRENT']);
        try {
            $service->commit($building, $manager, $plan['rows']);
            $this->fail('Stale number change accepted');
        } catch (ValidationException) {
            $this->assertSame('CONCURRENT', $unit->fresh()->unit_number);
        }
    }

    public function test_two_rows_cannot_claim_the_same_new_number(): void
    {
        [$manager, $unit, $building] = $this->scenario();
        $second = Unit::create(['floor_id' => $unit->floor_id, 'unit_number' => 'SECOND', 'area' => 90, 'usage_type' => 'residential']);
        $rows = $this->exportedRows($building);
        foreach ($rows as &$row) {
            if (in_array($row['unit'], [$unit->unit_number, 'SECOND'], true)) {
                $row['unit'] = 'SAME-NEW';
            }
        }
        unset($row);
        $this->expectException(ValidationException::class);
        app(BuildingWorkbookService::class)->commit($building, $manager, $rows);
    }

    public function test_workbook_protects_structure_and_only_unlocks_existing_input_cells(): void
    {
        [$manager, $unit, $building] = $this->scenario();
        $response = $this->get(route('management.user-import.template', $building))->assertOk();
        $path = $response->baseResponse->getFile()->getPathname();
        $zip = new \ZipArchive;
        $zip->open($path);
        $sheet = simplexml_load_string($zip->getFromName('xl/worksheets/sheet1.xml'));
        $styles = simplexml_load_string($zip->getFromName('xl/styles.xml'));
        $this->assertSame('true', (string) $sheet->sheetProtection['sheet']);
        foreach (['insertColumns', 'deleteColumns', 'insertRows', 'deleteRows', 'sort'] as $attribute) {
            $this->assertSame('true', (string) $sheet->sheetProtection[$attribute]);
        }
        $seen = [];
        foreach ($sheet->sheetData->row as $row) {
            foreach ($row->c as $cell) {
                $reference = (string) $cell['r'];
                $style = $styles->cellXfs->xf[(int) $cell['s']];
                $unlocked = (string) $style->protection['locked'] === '0';
                $expected = preg_match('/^[E-Q][0-9]+$/', $reference) && (int) $row['r'] > 1;
                $this->assertSame((bool) $expected, $unlocked, $reference);
                $seen[$reference] = true;
            }
        }
        foreach (range('E', 'Q') as $column) {
            $this->assertArrayHasKey($column.'2', $seen, 'Blank input cells must also be editable.');
        }
        $this->assertStringContainsString('lockStructure="true"', $zip->getFromName('xl/workbook.xml'));
        $zip->close();
        unlink($path);
    }

    public function test_import_existing_user_in_multiple_units_does_not_duplicate_or_reset_account(): void
    {
        [$manager, $unit, $building] = $this->scenario();
        $user = User::factory()->create(['is_active' => true, 'is_blocked' => false]);
        $other = Unit::where('floor_id', $unit->floor_id)->where('id', '!=', $unit->id)->firstOrFail();
        $rows = [];
        foreach ([$unit, $other] as $target) {
            $rows[] = array_combine(BuildingUserImportService::HEADERS, [(string) $target->id, (string) $target->unit_number, $user->first_name, $user->last_name, $user->mobile, 'owner', '', today()->toDateString()]);
        }
        $password = $user->password;
        app(BuildingUserImportService::class)->commit($building, $manager, $rows);
        app(BuildingUserImportService::class)->commit($building, $manager, $rows);
        $this->assertSame($password, $user->fresh()->password);
        $this->assertSame(2, UnitOwnership::where('user_id', $user->id)->count());
    }
}
