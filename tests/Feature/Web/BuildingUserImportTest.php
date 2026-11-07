<?php

namespace Tests\Feature\Web;

use App\Models\Unit;
use App\Models\UnitOwnership;
use App\Models\User;
use App\Models\Wallet;
use App\Services\OccupancyService;
use App\Services\Web\BuildingUserImportService;
use Database\Seeders\AccessScenarioSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
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
        $this->actingAs($manager);

        return [$manager, $unit, $unit->floor->block->building];
    }

    private function file(array $rows): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'import-test');
        $writer = new Writer;
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues(BuildingUserImportService::HEADERS));
        foreach ($rows as $row) {
            $writer->addRow(Row::fromValues($row));
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
        $file = $this->file([[(string) $unit->id, (string) $unit->unit_number, 'آزمایش', 'اکسل', '09129998877', 'owner_resident', '3', today()->toDateString()]]);
        $this->post(route('management.user-import.preview', $building), ['file' => $file])->assertOk()->assertSee('تأیید و ثبت');
        $this->assertDatabaseMissing('users', ['mobile' => '09129998877']);
        $token = session('building_user_import.token');
        $this->post(route('management.user-import.commit', $building), ['token' => $token])->assertRedirect();
        $user = User::where('mobile', '09129998877')->firstOrFail();
        $this->assertTrue($user->wallets()->exists());
        $this->assertDatabaseHas('unit_ownerships', ['unit_id' => $unit->id, 'user_id' => $user->id]);
        $this->assertDatabaseHas('unit_occupancies', ['unit_id' => $unit->id, 'user_id' => $user->id]);
        $this->post(route('management.user-import.commit', $building), ['token' => $token])->assertStatus(419);
    }

    public function test_foreign_units_and_mismatched_existing_accounts_are_rejected(): void
    {
        [$manager, $unit, $building] = $this->scenario();
        $foreign = Unit::whereHas('floor.block', fn ($q) => $q->where('building_id', '!=', $building->id))->firstOrFail();
        foreach ([[$foreign, '09129998877', 'جدید'], [$unit, $manager->mobile, 'نام نادرست']] as [$target, $mobile, $name]) {
            $file = $this->file([[(string) $target->id, (string) $target->unit_number, $name, '', $mobile, 'owner', '', today()->toDateString()]]);
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
        $this->post(route('management.user-import.preview', $building), ['file' => $this->file([array_values($rows[0])])])->assertOk();
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
