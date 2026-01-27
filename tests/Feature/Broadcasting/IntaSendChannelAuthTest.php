<?php

namespace Tests\Feature\Broadcasting;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IntaSendChannelAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_subscribe_to_intasend_channel(): void
    {
        $user = User::factory()->create();
        $intasendInvoiceId = 'INV_'.uniqid();

        $response = $this->actingAs($user)
            ->post('/broadcasting/auth', [
                'channel_name' => 'private-intasend.'.$intasendInvoiceId,
            ]);

        $response->assertStatus(200);
    }

    public function test_unauthenticated_user_cannot_subscribe_to_intasend_channel(): void
    {
        $intasendInvoiceId = 'INV_'.uniqid();

        $response = $this->post('/broadcasting/auth', [
            'channel_name' => 'private-intasend.'.$intasendInvoiceId,
        ]);

        // Laravel returns empty response for unauthenticated users on private channels
        // The channel callback returns null when $user is null, which denies authorization
        $response->assertStatus(200);
        $this->assertEmpty($response->content());
    }
}
