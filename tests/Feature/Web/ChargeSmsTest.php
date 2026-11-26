<?php

namespace Tests\Feature\Web;

use App\Contracts\Notifications\SmsSender;
use App\Models\Building;
use App\Models\ChargePeriod;
use App\Models\ChargeSmsNotice;
use App\Models\UnitInvoice;
use App\Models\UnitOwnership;
use App\Models\User;
use Database\Seeders\AccessScenarioSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ChargeSmsTest extends TestCase
{
    use RefreshDatabase;

    private function scenario(): array
    {
        $this->seed(AccessScenarioSeeder::class);
        $owner = User::where('email', 'role.owner@buildino.local')->firstOrFail();
        $manager = User::where('email', 'role.building@buildino.local')->firstOrFail();
        $unit = UnitOwnership::where('user_id', $owner->id)->firstOrFail()->unit;
        $period = ChargePeriod::create(['building_id' => $unit->floor->block->building_id, 'title' => 'مهر ۱۴۰۵', 'period_start' => '2026-09-23', 'period_end' => '2026-10-22', 'due_date' => '2026-10-25', 'status' => 'issued', 'created_by' => $manager->id]);
        config(['notifications.sms_provider' => 'ghasedak', 'notifications.sms_templates.charge.notice.template' => 'buildinodebit']);
        $this->actingAs($manager);

        return [$owner, $manager, $unit, $period];
    }

    private function invoice($unit, $period, $owner, $amount = 1000): UnitInvoice
    {
        return UnitInvoice::create(['building_id' => $period->building_id, 'unit_id' => $unit->id, 'charge_period_id' => $period->id, 'invoice_number' => 'SMS-'.Str::uuid(), 'payer_responsibility' => $owner ? 'owner' : 'unit', 'payer_user_id' => $owner?->id, 'issue_date' => today(), 'due_date' => today(), 'period_start' => $period->period_start, 'period_end' => $period->period_end, 'subtotal' => $amount, 'total_amount' => $amount, 'outstanding_amount' => $amount, 'paid_amount' => 0, 'status' => 'issued']);
    }

    public function test_aggregates_and_sends_once_per_period_with_snapshot(): void
    {
        [$owner,$manager,$unit,$period] = $this->scenario();
        $this->invoice($unit, $period, $owner, 1000);
        $second = $unit->replicate();
        $second->unit_number = 'SMS-2';
        $second->save();
        $this->invoice($second, $period, $owner, 2000);
        $this->mock(SmsSender::class, fn ($m) => $m->shouldReceive('send')->once()->withArgs(fn ($mobile, $msg) => $mobile === $owner->mobile && $msg->purpose === 'charge.notice' && $msg->parameters['param3'] === '3000')->andReturn(['accepted' => true, 'provider_message_id' => 'sms-1']));
        $this->get('/management/operations/charge-sms?building_id='.$period->building_id.'&period_id='.$period->id)->assertOk()->assertSee('3,000');
        $url = route('management.charge-sms.send', $period);
        $this->postJson($url, ['user_id' => $owner->id])->assertOk()->assertJsonPath('status', 'accepted');
        $this->postJson($url, ['user_id' => $owner->id])->assertOk()->assertJsonPath('status', 'accepted');
        $this->assertSame(1, ChargeSmsNotice::count());
        $this->assertSame(3000, ChargeSmsNotice::first()->amount);
        $this->get('/management/operations/charge-sms?building_id='.$period->building_id.'&period_id='.$period->id)->assertOk()->assertSee('پذیرفته‌شده توسط قاصدک');
    }

    public function test_missing_payer_requires_authorized_mapping_and_template(): void
    {
        [$owner,$manager,$unit,$period] = $this->scenario();
        $this->invoice($unit, $period, null);
        $this->mock(SmsSender::class, fn ($m) => $m->shouldNotReceive('send'));
        $this->get('/management/operations/charge-sms?building_id='.$period->building_id.'&period_id='.$period->id)->assertOk()->assertSee('تعیین مخاطب');
        $url = route('management.charge-sms.send', $period);
        $this->postJson($url, ['user_id' => $owner->id])->assertUnprocessable();
        $assign = route('management.charge-sms.assign', $period);
        $this->post($assign, ['unit_id' => $unit->id, 'user_id' => $manager->id])->assertUnprocessable();
        $this->post($assign, ['unit_id' => $unit->id, 'user_id' => $owner->id])->assertRedirect()->assertSessionHasNoErrors();
        config(['notifications.sms_templates.charge.notice.template' => null]);
        $this->postJson($url, ['user_id' => $owner->id])->assertUnprocessable();
        $this->assertSame(0, ChargeSmsNotice::count());
    }

    public function test_uncertain_response_is_not_retried_and_other_users_cannot_send(): void
    {
        [$owner,$manager,$unit,$period] = $this->scenario();
        $this->invoice($unit, $period, $owner);
        $this->mock(SmsSender::class, fn ($m) => $m->shouldReceive('send')->once()->andThrow(new \RuntimeException('timeout')));
        $url = route('management.charge-sms.send', $period);
        $this->postJson($url, ['user_id' => $owner->id])->assertOk()->assertJsonPath('status', 'unknown');
        $this->postJson($url, ['user_id' => $owner->id])->assertOk()->assertJsonPath('status', 'unknown');
        $this->post(route('management.charge-sms.assign', $period), ['unit_id' => $unit->id, 'user_id' => $owner->id])->assertSessionHasErrors('recipient');
        $this->actingAs(User::where('email', 'role.operator@buildino.local')->firstOrFail())->postJson($url, ['user_id' => $owner->id])->assertForbidden();
    }

    public function test_debit_template_uses_name_jalali_month_and_calculated_rials_even_after_payment(): void
    {
        [$owner, $manager, $unit, $period] = $this->scenario();
        $period->building->update(['currency' => 'IRT']);
        $period->update(['title' => 'عنوان دلخواه مدیر']);
        $invoice = $this->invoice($unit, $period, $owner, 3000);
        $invoice->update(['paid_amount' => 3000, 'outstanding_amount' => 0, 'status' => 'paid']);
        $this->mock(SmsSender::class, fn ($m) => $m->shouldReceive('send')->once()
            ->withArgs(fn ($mobile, $message) => $message->parameters === [
                'param1' => trim($owner->first_name.' '.$owner->last_name),
                'param2' => 'مهر 1405', 'param3' => '30000',
            ])->andReturn(['accepted' => true]));
        $this->postJson(route('management.charge-sms.send', $period), ['user_id' => $owner->id])->assertOk()->assertJsonPath('status', 'accepted');
        $this->assertDatabaseHas('charge_sms_notices', ['amount' => 30000, 'currency' => 'IRR', 'template' => 'buildinodebit']);
    }

    public function test_draft_and_foreign_building_periods_cannot_send(): void
    {
        [$owner,$manager,$unit,$period] = $this->scenario();
        $this->mock(SmsSender::class, fn ($m) => $m->shouldNotReceive('send'));
        $period->update(['status' => 'calculated']);
        $this->postJson(route('management.charge-sms.send', $period), ['user_id' => $owner->id])->assertUnprocessable();
        $period->update(['status' => 'issued', 'building_id' => Building::where('id', '!=', $period->building_id)->firstOrFail()->id]);
        $this->postJson(route('management.charge-sms.send',$period),['user_id' => $owner->id])->assertForbidden();
    }
}
