<?php

declare(strict_types=1);

namespace Tests\Feature\Owners;

use App\Mail\OwnerPayoutMail;
use App\Models\Notification;
use App\Models\NotificationPreference;
use App\Models\OwnerPayout;
use App\Models\PropertyOwner;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * Phase-104 OWNER-REMITTANCE-NOTIFY: a payout remittance advice (PDF email) + in-app owner
 * notifications (owner_payout_sent / owner_statement_sent) + the owner notifications surface.
 */
class Phase104OwnerRemittanceNotifyTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    /** An owner WITH a portal login (so in-app notifications can land). */
    private function ownerWithLogin(User $landlord, array $extra = []): array
    {
        $user = Model::withoutEvents(fn () => User::factory()->create([
            'role' => 'owner', 'landlord_id' => $landlord->id, 'email_verified_at' => now(),
        ]));
        $owner = PropertyOwner::factory()->forLandlord($landlord)->create(array_merge(['user_id' => $user->id], $extra));

        return [$user, $owner];
    }

    // --- REMITTANCE ON RECORD --------------------------------------------

    public function test_recording_a_payout_emails_an_advice_and_notifies_in_app(): void
    {
        Mail::fake();
        $setup = $this->createLandlordWithFullSetup();
        [$user, $owner] = $this->ownerWithLogin($setup['landlord'], ['email' => 'owner@example.com']);

        $this->actingAs($setup['landlord']->fresh())
            ->post(route('finances.owners.payouts.store', $owner->id), [
                'amount' => 15000, 'paid_on' => now()->format('Y-m-d'), 'method' => 'bank_transfer',
            ])->assertRedirect();

        Mail::assertQueued(OwnerPayoutMail::class, fn (OwnerPayoutMail $m) => $m->hasTo('owner@example.com'));
        $this->assertDatabaseHas('notifications', [
            'recipient_id' => $user->id,
            'type' => Notification::TYPE_OWNER_PAYOUT_SENT,
            'channel' => 'in_app',
        ]);
    }

    public function test_payout_for_an_owner_without_a_login_records_but_no_in_app(): void
    {
        Mail::fake();
        $setup = $this->createLandlordWithFullSetup();
        // No user_id (no portal login), but has email → advice still emailed.
        $owner = PropertyOwner::factory()->forLandlord($setup['landlord'])->create(['email' => 'noportal@example.com', 'user_id' => null]);

        $this->actingAs($setup['landlord']->fresh())
            ->post(route('finances.owners.payouts.store', $owner->id), [
                'amount' => 5000, 'paid_on' => now()->format('Y-m-d'), 'method' => 'cash',
            ])->assertRedirect();

        $this->assertDatabaseHas('owner_payouts', ['property_owner_id' => $owner->id, 'amount' => 5000]);
        Mail::assertQueued(OwnerPayoutMail::class);
        $this->assertDatabaseMissing('notifications', ['type' => Notification::TYPE_OWNER_PAYOUT_SENT]);
    }

    public function test_payout_for_an_owner_without_email_records_but_no_advice_email(): void
    {
        Mail::fake();
        $setup = $this->createLandlordWithFullSetup();
        [, $owner] = $this->ownerWithLogin($setup['landlord'], ['email' => null]);

        $this->actingAs($setup['landlord']->fresh())
            ->post(route('finances.owners.payouts.store', $owner->id), [
                'amount' => 5000, 'paid_on' => now()->format('Y-m-d'), 'method' => 'cash',
            ])->assertRedirect();

        Mail::assertNothingQueued();
        // ...but the in-app notification still lands (owner has a login).
        $this->assertDatabaseHas('notifications', ['type' => Notification::TYPE_OWNER_PAYOUT_SENT]);
    }

    public function test_owner_can_opt_out_of_the_in_app_payout_notice(): void
    {
        Mail::fake();
        $setup = $this->createLandlordWithFullSetup();
        [$user, $owner] = $this->ownerWithLogin($setup['landlord'], ['email' => 'owner@example.com']);

        // Opt out of the in-app payout type for this owner.
        NotificationPreference::getOrCreate($user->id, $setup['landlord']->id)
            ->update(['owner_payout_sent_enabled' => false]);

        $this->actingAs($setup['landlord']->fresh())
            ->post(route('finances.owners.payouts.store', $owner->id), [
                'amount' => 5000, 'paid_on' => now()->format('Y-m-d'), 'method' => 'cash',
            ])->assertRedirect();

        // The document email still goes (it's the record), but no in-app notice.
        Mail::assertQueued(OwnerPayoutMail::class);
        $this->assertDatabaseMissing('notifications', ['recipient_id' => $user->id, 'type' => Notification::TYPE_OWNER_PAYOUT_SENT]);
    }

    // --- RESEND ----------------------------------------------------------

    public function test_landlord_can_resend_a_remittance_advice(): void
    {
        Mail::fake();
        $setup = $this->createLandlordWithFullSetup();
        [, $owner] = $this->ownerWithLogin($setup['landlord'], ['email' => 'owner@example.com']);
        $payout = OwnerPayout::factory()->forOwner($owner)->create(['amount' => 7000]);

        $this->actingAs($setup['landlord']->fresh())
            ->post(route('finances.owners.payouts.advice', ['owner' => $owner->id, 'payout' => $payout->id]))
            ->assertRedirect();

        Mail::assertQueued(OwnerPayoutMail::class);
    }

    public function test_cannot_resend_advice_for_a_voided_payout(): void
    {
        Mail::fake();
        $setup = $this->createLandlordWithFullSetup();
        [, $owner] = $this->ownerWithLogin($setup['landlord'], ['email' => 'owner@example.com']);
        $payout = OwnerPayout::factory()->forOwner($owner)->voided()->create();

        $this->actingAs($setup['landlord']->fresh())
            ->post(route('finances.owners.payouts.advice', ['owner' => $owner->id, 'payout' => $payout->id]))
            ->assertRedirect();

        Mail::assertNothingQueued();
    }

    public function test_cannot_resend_advice_for_a_foreign_owners_payout(): void
    {
        Mail::fake();
        $setup = $this->createLandlordWithFullSetup();
        $other = $this->createLandlordWithFullSetup();
        [, $foreign] = $this->ownerWithLogin($other['landlord'], ['email' => 'foreign@example.com']);
        $payout = OwnerPayout::factory()->forOwner($foreign)->create();

        $status = $this->actingAs($setup['landlord']->fresh())
            ->post(route('finances.owners.payouts.advice', ['owner' => $foreign->id, 'payout' => $payout->id]))
            ->getStatusCode();

        $this->assertContains($status, [403, 404]);
        Mail::assertNothingQueued();
    }

    // --- STATEMENT NOTIFICATION ------------------------------------------

    public function test_emailing_a_statement_creates_an_in_app_notice(): void
    {
        Mail::fake();
        $setup = $this->createLandlordWithFullSetup();
        [$user, $owner] = $this->ownerWithLogin($setup['landlord'], ['email' => 'owner@example.com']);

        $this->actingAs($setup['landlord']->fresh())
            ->post(route('finances.owners.statement.email', $owner->id), ['period' => '12'])
            ->assertRedirect();

        $this->assertDatabaseHas('notifications', [
            'recipient_id' => $user->id,
            'type' => Notification::TYPE_OWNER_STATEMENT_SENT,
            'channel' => 'in_app',
        ]);
    }

    // --- OWNER NOTIFICATIONS SURFACE -------------------------------------

    private function notify(User $recipient, int $landlordId, string $type = Notification::TYPE_OWNER_PAYOUT_SENT, array $extra = []): Notification
    {
        return Notification::create(array_merge([
            'landlord_id' => $landlordId,
            'recipient_id' => $recipient->id,
            'type' => $type,
            'urgency' => Notification::getUrgencyForType($type),
            'channel' => 'in_app',
            'subject' => 'Test',
            'message' => 'Test message',
            'status' => 'sent',
        ], $extra));
    }

    public function test_owner_notifications_page_lists_only_their_own(): void
    {
        $setup = $this->createLandlordWithFullSetup();
        [$userA] = $this->ownerWithLogin($setup['landlord']);
        [$userB] = $this->ownerWithLogin($setup['landlord']);
        $this->notify($userA, $setup['landlord']->id, Notification::TYPE_OWNER_PAYOUT_SENT, ['subject' => 'A-NOTICE']);
        $this->notify($userB, $setup['landlord']->id, Notification::TYPE_OWNER_PAYOUT_SENT, ['subject' => 'B-NOTICE']);

        $props = $this->actingAs($userA->fresh())
            ->get(route('owner-portal.notifications'))
            ->assertOk()
            ->viewData('page');

        $this->assertSame('Owner/Notifications', $props['component']);
        $subjects = collect($props['props']['notifications']['data'])->pluck('subject')->all();
        $this->assertContains('A-NOTICE', $subjects);
        $this->assertNotContains('B-NOTICE', $subjects);
    }

    public function test_owner_can_mark_a_notification_read_and_not_anothers(): void
    {
        $setup = $this->createLandlordWithFullSetup();
        [$userA] = $this->ownerWithLogin($setup['landlord']);
        [$userB] = $this->ownerWithLogin($setup['landlord']);
        $mine = $this->notify($userA, $setup['landlord']->id);
        $theirs = $this->notify($userB, $setup['landlord']->id);

        $this->actingAs($userA->fresh())
            ->patch(route('owner-portal.notifications.read', $mine->id))
            ->assertOk();
        $this->assertNotNull($mine->fresh()->read_at);

        // Cannot mark another owner's notification.
        $this->actingAs($userA->fresh())
            ->patch(route('owner-portal.notifications.read', $theirs->id))
            ->assertForbidden();
        $this->assertNull($theirs->fresh()->read_at);
    }

    public function test_owner_can_mark_all_read(): void
    {
        $setup = $this->createLandlordWithFullSetup();
        [$user] = $this->ownerWithLogin($setup['landlord']);
        $this->notify($user, $setup['landlord']->id);
        $this->notify($user, $setup['landlord']->id);

        $this->actingAs($user->fresh())
            ->patch(route('owner-portal.notifications.read-all'))
            ->assertOk();

        $this->assertSame(0, Notification::withoutGlobalScope('landlord')
            ->where('recipient_id', $user->id)->whereNull('read_at')->count());
    }

    public function test_non_owner_cannot_access_owner_notifications(): void
    {
        $setup = $this->createLandlordWithFullSetup();
        $this->actingAs($setup['landlord']->fresh())->get(route('owner-portal.notifications'))->assertForbidden();
    }
}
