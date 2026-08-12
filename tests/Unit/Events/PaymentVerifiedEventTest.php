<?php

namespace Tests\Unit\Events;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Events\PaymentVerified;
use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\Support\CreatesBuildingDomainData;
use Tests\TestCase;

class PaymentVerifiedEventTest extends TestCase
{
    use RefreshDatabase, CreatesBuildingDomainData;

    public function test_payment_verification_dispatches_domain_event(): void
    {
        Event::fake([PaymentVerified::class]);

        $graph = $this->createBuildingGraph();
        $user = $this->createUser();

        $payment = Payment::query()->create([
            'uuid' => (string) str()->uuid(),
            'building_id' => $graph['building']->id,
            'payer_user_id' => $user->id,
            'payment_number' => 'PAY-EVENT-1',
            'amount' => 100000,
            'currency' => 'IRR',
            'method' => PaymentMethod::Manual,
            'status' => PaymentStatus::Pending,
        ]);

        app(PaymentService::class)->verify($payment, $user);

        Event::assertDispatched(PaymentVerified::class);
    }
}
