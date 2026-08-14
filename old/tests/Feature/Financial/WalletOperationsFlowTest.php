<?php

namespace Tests\Feature\Financial;

use App\Enums\BuildingBillPaymentStatus;
use App\Enums\BuildingBillType;
use App\Enums\FacilityWalletPayerSource;
use App\Enums\ReservationApprovalType;
use App\Enums\ReservationStatus;
use App\Enums\WalletPayoutStatus;
use App\Enums\WalletTransferType;
use App\Models\Block;
use App\Models\Building;
use App\Models\BuildingBankAccount;
use App\Models\BuildingFacility;
use App\Models\Complex;
use App\Models\FacilityReservation;
use App\Models\Floor;
use App\Models\ReservationCancellation;
use App\Models\Unit;
use App\Models\User;
use App\Services\Facility\FacilityWalletPaymentService;
use App\Services\Wallet\BuildingBillPaymentService;
use App\Services\Wallet\WalletPayoutService;
use App\Services\Wallet\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WalletOperationsFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_paid_facility_reservation_moves_money_to_building_wallet_and_refunds_same_source(): void
    {
        $graph = $this->createGraph('FAC');
        $user = $this->createUser(
            '09125550001',
            'facility-wallet@example.test'
        );

        $facility = BuildingFacility::query()->create([
            'building_id' => $graph['building']->id,
            'title' => 'Pool',
            'code' => 'POOL',
            'type' => 'pool',
            'default_price' => 500000,
            'requires_payment' => true,
            'requires_approval' => false,
            'is_active' => true,
        ]);

        $reservation = FacilityReservation::query()->create([
            'uuid' => (string) str()->uuid(),
            'building_facility_id' => $facility->id,
            'unit_id' => $graph['unit']->id,
            'user_id' => $user->id,
            'reservation_date' => now()->addDay()->toDateString(),
            'start_time' => '10:00',
            'end_time' => '11:00',
            'price' => 500000,
            'discount_amount' => 0,
            'final_amount' => 500000,
            'status' => ReservationStatus::PaymentPending,
            'approval_type' => ReservationApprovalType::Automatic,
        ]);

        $wallets = app(WalletService::class);
        $userWallet = $wallets->walletFor($user);
        $buildingWallet = $wallets->walletFor($graph['building']);

        $wallets->credit(
            $userWallet,
            500000,
            WalletTransferType::TopUp,
            'test:facility-topup'
        );

        $service = app(FacilityWalletPaymentService::class);

        $payment = $service->pay(
            $reservation,
            $user,
            FacilityWalletPayerSource::UserWallet
        );

        $reservation->refresh();

        $this->assertSame(
            ReservationStatus::Approved,
            $reservation->status
        );

        $this->assertSame(
            0,
            (int) $userWallet->fresh()->balance
        );

        $this->assertSame(
            500000,
            (int) $buildingWallet->fresh()->balance
        );

        $this->assertSame(
            500000,
            (int) $payment->amount
        );

        $cancellation = ReservationCancellation::query()->create([
            'facility_reservation_id' => $reservation->id,
            'cancelled_by' => $user->id,
            'reason' => 'Test refund',
            'cancellation_fee' => 100000,
            'refund_amount' => 400000,
            'refund_status' => 'pending',
            'cancelled_at' => now(),
        ]);

        $service->refund(
            $cancellation,
            $user
        );

        $this->assertSame(
            400000,
            (int) $userWallet->fresh()->balance
        );

        $this->assertSame(
            100000,
            (int) $buildingWallet->fresh()->balance
        );

        $this->assertDatabaseHas(
            'reservation_cancellations',
            [
                'id' => $cancellation->id,
                'refund_status' => 'refunded',
            ]
        );
    }

    public function test_payout_locks_balance_then_debits_only_after_support_marks_paid(): void
    {
        $graph = $this->createGraph('PAYOUT');

        $manager = $this->createUser(
            '09125551001',
            'payout-manager@example.test'
        );

        $support = $this->createUser(
            '09125551002',
            'payout-support@example.test'
        );

        $bank = BuildingBankAccount::query()->create([
            'building_id' => $graph['building']->id,
            'bank_name' => 'Test Bank',
            'account_holder_name' => 'Building Manager',
            'iban' => 'IR000000000000000000000001',
            'is_default' => true,
            'is_verified' => true,
            'is_active' => true,
            'verified_by' => $support->id,
            'verified_at' => now(),
        ]);

        $wallets = app(WalletService::class);
        $buildingWallet = $wallets->walletFor(
            $graph['building']
        );

        $wallets->credit(
            $buildingWallet,
            1000000,
            WalletTransferType::TopUp,
            'test:payout-topup'
        );

        $service = app(WalletPayoutService::class);

        $request = $service->request(
            $graph['building'],
            $bank,
            $manager,
            600000
        );

        $this->assertSame(
            WalletPayoutStatus::Pending,
            $request->status
        );

        $this->assertSame(
            1000000,
            (int) $buildingWallet->fresh()->balance
        );

        $this->assertSame(
            600000,
            (int) $buildingWallet->fresh()->locked_balance
        );

        $service->approve(
            $request,
            $support
        );

        $request = $service->markPaid(
            $request->fresh(),
            $support,
            'BANK-REF-001'
        );

        $this->assertSame(
            WalletPayoutStatus::Paid,
            $request->status
        );

        $this->assertSame(
            400000,
            (int) $buildingWallet->fresh()->balance
        );

        $this->assertSame(
            0,
            (int) $buildingWallet->fresh()->locked_balance
        );

        $this->assertNotNull(
            $request->wallet_transfer_id
        );
    }

    public function test_bill_payment_reserves_balance_and_failure_unlocks_it(): void
    {
        $graph = $this->createGraph('BILL');

        $manager = $this->createUser(
            '09125552001',
            'bill-manager@example.test'
        );

        $operator = $this->createUser(
            '09125552002',
            'bill-operator@example.test'
        );

        $wallets = app(WalletService::class);
        $buildingWallet = $wallets->walletFor(
            $graph['building']
        );

        $wallets->credit(
            $buildingWallet,
            800000,
            WalletTransferType::TopUp,
            'test:bill-topup'
        );

        $service = app(BuildingBillPaymentService::class);

        $bill = $service->request(
            $graph['building'],
            $manager,
            BuildingBillType::Electricity,
            300000,
            [
                'bill_identifier' => '123',
                'payment_identifier' => '456',
                'provider' => 'test-provider',
            ]
        );

        $this->assertSame(
            300000,
            (int) $buildingWallet->fresh()->locked_balance
        );

        $service->fail(
            $bill,
            $operator,
            'Provider rejected payment'
        );

        $this->assertSame(
            BuildingBillPaymentStatus::Failed,
            $bill->fresh()->status
        );

        $this->assertSame(
            800000,
            (int) $buildingWallet->fresh()->balance
        );

        $this->assertSame(
            0,
            (int) $buildingWallet->fresh()->locked_balance
        );

        $paidBill = $service->request(
            $graph['building'],
            $manager,
            BuildingBillType::Gas,
            250000,
            [
                'bill_identifier' => '789',
                'payment_identifier' => '012',
            ]
        );

        $service->complete(
            $paidBill,
            $operator,
            'PROVIDER-REF-01',
            ['accepted' => true]
        );

        $this->assertSame(
            BuildingBillPaymentStatus::Paid,
            $paidBill->fresh()->status
        );

        $this->assertSame(
            550000,
            (int) $buildingWallet->fresh()->balance
        );

        $this->assertSame(
            0,
            (int) $buildingWallet->fresh()->locked_balance
        );
    }

    private function createGraph(string $suffix): array
    {
        $complex = Complex::query()->create([
            'code' => 'CMP-'.$suffix,
            'title' => 'Complex '.$suffix,
            'province' => 'Tehran',
            'city' => 'Tehran',
            'is_active' => true,
        ]);

        $building = Building::query()->create([
            'complex_id' => $complex->id,
            'code' => 'BLD-'.$suffix,
            'title' => 'Building '.$suffix,
            'currency' => 'IRR',
            'is_active' => true,
        ]);

        $block = Block::query()->create([
            'building_id' => $building->id,
            'title' => 'Block '.$suffix,
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $floor = Floor::query()->create([
            'block_id' => $block->id,
            'floor_number' => 1,
            'title' => 'Floor '.$suffix,
            'sort_order' => 1,
        ]);

        $unit = Unit::query()->create([
            'floor_id' => $floor->id,
            'unit_number' => '101-'.$suffix,
            'title' => 'Unit '.$suffix,
            'area' => 100,
            'bedrooms' => 2,
            'usage_type' => 'residential',
            'is_active' => true,
        ]);

        return compact(
            'complex',
            'building',
            'block',
            'floor',
            'unit'
        );
    }

    private function createUser(
        string $mobile,
        string $email
    ): User {
        return User::query()->create([
            'first_name' => 'Wallet',
            'last_name' => 'Operator',
            'mobile' => $mobile,
            'email' => $email,
            'mobile_verified_at' => now(),
            'email_verified_at' => now(),
            'password' => 'TestPassword123!',
            'is_active' => true,
            'is_blocked' => false,
        ]);
    }
}
