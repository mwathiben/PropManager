<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers;

use App\Models\NotificationTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * M2 decomposition safety net: characterizes the notification-template CRUD
 * routes BEFORE the template actions are split out of NotificationsController
 * into a dedicated NotificationTemplateController. These routes had no
 * coverage; the split is a verbatim move + route re-point (names unchanged),
 * so these lock the end-to-end behaviour.
 */
class NotificationTemplateControllerTest extends TestCase
{
    use CreatesTestData;
    use RefreshDatabase;

    public function test_landlord_can_view_templates_page(): void
    {
        ['landlord' => $landlord] = $this->createLandlordWithFullSetup();

        $this->actingAs($landlord)
            ->get(route('notifications.templates'))
            ->assertOk();
    }

    public function test_landlord_can_store_a_template(): void
    {
        ['landlord' => $landlord] = $this->createLandlordWithFullSetup();

        $this->actingAs($landlord)
            ->post(route('notifications.templates.store'), [
                'name' => 'My Rent Reminder',
                'type' => 'rent_reminder',
                'subject' => 'Rent Due',
                'body' => 'Hello {{tenant_name}}, your rent is due.',
                'is_active' => true,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('notification_templates', [
            'landlord_id' => $landlord->id,
            'name' => 'My Rent Reminder',
            'type' => 'rent_reminder',
        ]);
    }

    public function test_landlord_can_preview_a_template(): void
    {
        ['landlord' => $landlord] = $this->createLandlordWithFullSetup();
        $template = NotificationTemplate::factory()->create([
            'landlord_id' => $landlord->id,
            'type' => 'rent_reminder',
        ]);

        $this->actingAs($landlord)
            ->postJson(route('notifications.templates.preview', $template))
            ->assertOk()
            ->assertJsonStructure(['subject', 'body']);
    }

    public function test_landlord_can_delete_own_non_default_template(): void
    {
        ['landlord' => $landlord] = $this->createLandlordWithFullSetup();
        $template = NotificationTemplate::factory()->create([
            'landlord_id' => $landlord->id,
            'is_default' => false,
        ]);

        $this->actingAs($landlord)
            ->delete(route('notifications.templates.destroy', $template))
            ->assertRedirect();

        $this->assertDatabaseMissing('notification_templates', ['id' => $template->id]);
    }

    public function test_cannot_modify_another_landlords_template(): void
    {
        ['landlord' => $landlord] = $this->createLandlordWithFullSetup();
        $other = User::factory()->create(['role' => 'landlord']);
        $template = NotificationTemplate::factory()->create(['landlord_id' => $other->id]);

        $response = $this->actingAs($landlord)
            ->delete(route('notifications.templates.destroy', $template));

        // Denied either by the authorizeTemplate guard (403) or tenant scope (404).
        $this->assertContains($response->status(), [403, 404]);
        $this->assertDatabaseHas('notification_templates', ['id' => $template->id]);
    }
}
