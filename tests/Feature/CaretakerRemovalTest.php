<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Building;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * The Operations → Team "remove caretaker" button (caretakers.destroy):
 * severs the caretaker→landlord link + detaches them from the landlord's
 * buildings. A landlord may only remove their own caretaker.
 */
class CaretakerRemovalTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    public function test_landlord_removes_their_caretaker(): void
    {
        $setup = $this->createLandlordWithFullSetup();
        $landlord = $setup['landlord'];
        $building = $setup['building'];

        $caretaker = User::factory()->create(['role' => 'caretaker', 'landlord_id' => $landlord->id]);
        $building->update(['caretaker_id' => $caretaker->id]);

        $this->actingAs($landlord)
            ->delete(route('caretakers.destroy', $caretaker->id))
            ->assertRedirect();

        $this->assertNull($caretaker->fresh()->landlord_id);
        $this->assertNull($building->fresh()->caretaker_id);
    }

    public function test_landlord_cannot_remove_another_landlords_caretaker(): void
    {
        $landlord = $this->createLandlordWithFullSetup()['landlord'];
        $otherSetup = $this->createLandlordWithFullSetup();
        $otherLandlord = $otherSetup['landlord'];

        $foreignCaretaker = User::factory()->create(['role' => 'caretaker', 'landlord_id' => $otherLandlord->id]);
        $otherSetup['building']->update(['caretaker_id' => $foreignCaretaker->id]);

        $this->actingAs($landlord)
            ->delete(route('caretakers.destroy', $foreignCaretaker->id))
            ->assertNotFound();

        $this->assertSame((int) $otherLandlord->id, (int) $foreignCaretaker->fresh()->landlord_id);
        $this->assertSame((int) $foreignCaretaker->id, (int) $otherSetup['building']->fresh()->caretaker_id);
    }

    public function test_removal_only_detaches_the_landlords_own_buildings(): void
    {
        $setup = $this->createLandlordWithFullSetup();
        $landlord = $setup['landlord'];
        $caretaker = User::factory()->create(['role' => 'caretaker', 'landlord_id' => $landlord->id]);
        $setup['building']->update(['caretaker_id' => $caretaker->id]);

        // A building owned by someone else that happens to reference the same
        // caretaker id must NOT be touched by this landlord's removal.
        $foreign = $this->createLandlordWithFullSetup();
        $foreign['building']->update(['caretaker_id' => $caretaker->id]);

        $this->actingAs($landlord)
            ->delete(route('caretakers.destroy', $caretaker->id))
            ->assertRedirect();

        $this->assertNull($setup['building']->fresh()->caretaker_id);
        $this->assertSame((int) $caretaker->id, (int) $foreign['building']->fresh()->caretaker_id);
    }
}
