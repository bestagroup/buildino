<?php

namespace Tests\Feature\Web;

use App\Contracts\Notifications\SmsSender;
use App\Data\Notifications\SmsTemplateMessage;
use App\Models\Building;
use App\Models\Unit;
use App\Models\UnitInvitation;
use App\Models\UnitOwnership;
use App\Models\User;
use Database\Seeders\AccessScenarioSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BuildingInvitationPageTest extends TestCase
{
    use RefreshDatabase;

    private function scenario(): array
    {
        $this->seed(AccessScenarioSeeder::class);
        $manager = User::where('email', 'role.building@buildino.local')->firstOrFail();
        $owner = User::where('email', 'role.owner@buildino.local')->firstOrFail();
        $unit = UnitOwnership::where('user_id', $owner->id)->firstOrFail()->unit->load('floor.block.building');
        $sms = new class implements SmsSender
        {
            public array $messages = [];

            public function send(string $mobile, string|SmsTemplateMessage $message): array
            {
                $this->messages[] = $message;

                return ['accepted' => true];
            }
        };
        $this->app->instance(SmsSender::class, $sms);
        $this->actingAs($manager, 'web');

        return [$manager, $owner, $unit, $unit->floor->block->building, $sms];
    }

    public function test_single_building_is_selected_and_history_is_visible(): void
    {
        [$manager, $owner, $unit, $building] = $this->scenario();
        $this->get('/management/operations/invitations')->assertOk()->assertSee($building->title)
            ->assertDontSee('id="invitation-building"', false)->assertSee('data-recipient', false)->assertSee('سوابق ارسال');
    }

    public function test_multiple_buildings_require_selection(): void
    {
        $this->scenario();
        $this->actingAs(User::where('email', 'role.superadmin@buildino.local')->firstOrFail(), 'web');
        $this->get('/management/operations/invitations')->assertOk()->assertSee('id="invitation-building"', false)
            ->assertDontSee('data-recipient', false);
    }

    public function test_send_duplicate_resend_and_used_invitation(): void
    {
        [$manager, $owner, $unit, $building, $sms] = $this->scenario();
        $url = route('management.building-invitations.send', $building);
        $payload = ['unit_id' => $unit->id, 'user_id' => $owner->id, 'expires_in_hours' => 72];
        $id = $this->postJson($url, $payload)->assertOk()->json('id');
        $this->postJson($url, $payload)->assertUnprocessable();
        $invitation = UnitInvitation::findOrFail($id);
        $hash = $invitation->invitation_code_hash;
        $invitation->update(['expires_at' => now()->subDay()]);
        $this->postJson($url, $payload + ['invitation_id' => $id])->assertOk();
        $this->assertNotSame($hash, $invitation->fresh()->invitation_code_hash);
        $this->assertTrue($invitation->fresh()->expires_at->isFuture());
        $invitation->update(['status' => 'accepted', 'accepted_at' => now(), 'accepted_user_id' => $owner->id]);
        $this->postJson($url, $payload + ['invitation_id' => $id])->assertUnprocessable();
        $this->assertCount(2, $sms->messages);
        $this->get('/management/operations/invitations')->assertOk()->assertSee('استفاده‌شده')
            ->assertDontSee('data-resend="'.$id.'"', false);
    }

    public function test_same_user_gets_independent_invitations_for_each_unit(): void
    {
        [$manager, $owner, $unit, $building, $sms] = $this->scenario();
        $otherUnit = Unit::where('floor_id', $unit->floor_id)->where('id', '!=', $unit->id)->firstOrFail();
        $ownership = UnitOwnership::where('user_id', $owner->id)->where('unit_id', $unit->id)->firstOrFail()->replicate();
        $ownership->unit_id = $otherUnit->id;
        $ownership->save();
        foreach ([$unit, $otherUnit] as $target) {
            $this->postJson(route('management.building-invitations.send', $building), ['unit_id' => $target->id, 'user_id' => $owner->id, 'expires_in_hours' => 72])->assertOk();
        }
        $this->assertSame(2, UnitInvitation::where('invited_user_id', $owner->id)->whereIn('unit_id', [$unit->id, $otherUnit->id])->count());
        $this->assertCount(2, $sms->messages);
        $this->assertNotSame($sms->messages[0]->parameters['param3'], $sms->messages[1]->parameters['param3']);
    }

    public function test_provider_failure_stays_pending_and_can_be_retried(): void
    {
        [$manager, $owner, $unit, $building] = $this->scenario();
        $this->app->instance(SmsSender::class, new class implements SmsSender
        {
            public function send(string $mobile, string|SmsTemplateMessage $message): array
            {
                throw new \RuntimeException('provider failure');
            }
        });
        $this->postJson(route('management.building-invitations.send', $building), ['unit_id' => $unit->id, 'user_id' => $owner->id, 'expires_in_hours' => 72])->assertStatus(502)->assertDontSee('provider failure');
        $this->assertDatabaseHas('unit_invitations', ['unit_id' => $unit->id, 'invited_user_id' => $owner->id, 'status' => 'pending', 'sent_at' => null]);
        $this->get('/management/operations/invitations')->assertOk()->assertSee('ارسال تأیید نشده')->assertSee('data-resend', false);
    }

    public function test_foreign_building_and_unlinked_user_cannot_be_invited(): void
    {
        [$manager, $owner, $unit, $building, $sms] = $this->scenario();
        $foreign = Building::where('id', '!=', $building->id)->firstOrFail();
        $this->get('/management/operations/invitations?building_id='.$foreign->id)->assertForbidden();
        $this->postJson(route('management.building-invitations.send', $foreign), ['unit_id' => $unit->id, 'user_id' => $owner->id, 'expires_in_hours' => 72])->assertForbidden();
        $unlinked = User::factory()->create();
        $this->postJson(route('management.building-invitations.send', $building), ['unit_id' => $unit->id, 'user_id' => $unlinked->id, 'expires_in_hours' => 72])->assertForbidden();
        $this->assertCount(0, $sms->messages);
    }
}
