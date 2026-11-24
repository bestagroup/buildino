<?php

namespace Tests\Feature\Financial;

use App\Contracts\FileScanner;
use App\Enums\FileScanStatus;
use App\Models\Unit;
use App\Models\UnitOwnership;
use App\Models\User;
use App\Models\WalletCashDocument;
use App\Services\Wallet\WalletService;
use Database\Seeders\AccessScenarioSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class WalletCashDocumentTest extends TestCase
{
    use RefreshDatabase;

    private function scenario(): array
    {
        $this->seed(AccessScenarioSeeder::class);
        $manager = User::where('email', 'role.building@buildino.local')->firstOrFail();
        $owner = User::where('email', 'role.owner@buildino.local')->firstOrFail();
        $unit = UnitOwnership::where('user_id', $owner->id)->firstOrFail()->unit;
        $building = $unit->floor->block->building;
        $this->actingAs($manager);

        return [$manager, $owner, $unit, $building];
    }

    private function data(string $direction, int $amount, string $number, ?int $unit = null): array
    {
        return ['uuid' => (string) Str::uuid(), 'direction' => $direction, 'amount' => $amount, 'document_number' => $number, 'unit_id' => $unit];
    }

    public function test_building_receipt_and_payment_are_recorded_once_without_image(): void
    {
        [$manager, $owner, $unit, $building] = $this->scenario();
        $this->get('/management/operations/wallet-cash')->assertOk()->assertSee('دریافت و پرداخت');
        $this->get('/management/operations')->assertOk()->assertSee('دریافت و پرداخت');
        $url = route('management.wallet-cash.store', $building);
        $data = $this->data('receipt', 5000, 'R-1');
        $this->post($url, $data)->assertSessionHasNoErrors()->assertRedirect();
        $this->post($url, $data)->assertSessionHasNoErrors();
        $data['uuid'] = (string) Str::uuid();
        $this->post($url, $data)->assertSessionHasNoErrors();
        $this->post($url, $this->data('payment', 1200, 'P-1'))->assertSessionHasNoErrors();
        $wallets = app(WalletService::class);
        $this->assertSame(3800, $wallets->walletFor($building)->balance);
        $this->assertSame(0, $wallets->walletFor($unit)->balance);
        $this->assertSame(2, WalletCashDocument::count());
        $this->assertSame(0, $wallets->walletFor($building)->locked_balance);
        $this->get(route('management.wallet-cash.index', ['building_id' => $building->id]))->assertOk()->assertSee('P-1');
    }

    public function test_unit_selection_changes_only_unit_wallet_and_replay_payload_must_match(): void
    {
        [$manager, $owner, $unit, $building] = $this->scenario();
        $url = route('management.wallet-cash.store', $building);
        $data = $this->data('receipt', 3000, 'U-1', $unit->id);
        $this->post($url, $data)->assertSessionHasNoErrors();
        $data['amount'] = 4000;
        $this->post($url, $data)->assertSessionHasErrors('document_number');
        $this->post($url, $this->data('payment', 500, 'U-2', $unit->id))->assertSessionHasNoErrors();
        $this->assertSame(2500, app(WalletService::class)->walletFor($unit)->balance);
        $this->assertSame(0, app(WalletService::class)->walletFor($building)->balance);
    }

    public function test_insufficient_or_locked_funds_do_not_leave_a_document_or_debit(): void
    {
        [$manager, $owner, $unit, $building] = $this->scenario();
        $url = route('management.wallet-cash.store', $building);
        $this->post($url, $this->data('receipt', 1000, 'R-1'))->assertSessionHasNoErrors();
        $wallet = app(WalletService::class)->walletFor($building);
        app(WalletService::class)->lockFunds($wallet, 800);
        $this->post($url, $this->data('payment', 300, 'P-FAIL'))->assertSessionHasErrors('balance');
        $this->assertSame(1000, $wallet->fresh()->balance);
        $this->assertSame(800, $wallet->fresh()->locked_balance);
        $this->assertSame(1, WalletCashDocument::count());
    }

    public function test_optional_image_is_private_and_scanner_failure_rolls_back_payment(): void
    {
        [$manager, $owner, $unit, $building] = $this->scenario();
        Storage::fake('private');
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jN1cAAAAASUVORK5CYII=');
        $url = route('management.wallet-cash.store', $building);
        $data = $this->data('receipt', 1000, 'IMAGE');
        $data['image'] = UploadedFile::fake()->createWithContent('receipt.png', $png);
        $this->post($url, $data)->assertSessionHasNoErrors();
        $document = WalletCashDocument::firstOrFail();
        $this->assertNotNull($document->file_id);
        $this->get(route('management.wallet-cash.image', $document))->assertOk()->assertDownload('receipt.png');
        $this->actingAs($owner)->get(route('management.wallet-cash.image', $document))->assertRedirect();
        $this->actingAs($manager);
        $this->mock(FileScanner::class, fn ($mock) => $mock->shouldReceive('scan')->once()->andReturn(FileScanStatus::Infected));
        $data = $this->data('payment', 500, 'INFECTED');
        $data['image'] = UploadedFile::fake()->createWithContent('receipt.png', $png);
        $this->post($url, $data)->assertSessionHasErrors('file');
        $wallet = app(WalletService::class)->walletFor($building);
        $this->assertSame(1000, $wallet->balance);
        $this->assertSame(0, $wallet->locked_balance);
        $this->assertSame(1, WalletCashDocument::count());
        $this->assertCount(1, Storage::disk('private')->allFiles());
    }

    public function test_scope_and_file_validation_are_enforced(): void
    {
        [$manager, $owner, $unit, $building] = $this->scenario();
        $foreign = Unit::whereHas('floor.block', fn ($q) => $q->where('building_id', '!=', $building->id))->firstOrFail();
        $url = route('management.wallet-cash.store', $building);
        $this->post($url, $this->data('receipt', 100, 'OTHER', $foreign->id))->assertNotFound();
        $this->get(route('management.wallet-cash.index', ['building_id' => $foreign->floor->block->building_id]))->assertForbidden();
        $data = $this->data('receipt', 100, 'FILE');
        $data['image'] = UploadedFile::fake()->createWithContent('bad.php', '<?php echo 1;');
        $this->post($url, $data)->assertSessionHasErrors('image');
        $this->actingAs($owner)->post($url, $this->data('receipt', 100, 'OWNER'))->assertRedirect();
        $this->assertSame(0, WalletCashDocument::count());
    }
}
