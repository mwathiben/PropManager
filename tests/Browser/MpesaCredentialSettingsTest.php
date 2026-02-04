<?php

namespace Tests\Browser;

use App\Models\PaymentConfiguration;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class MpesaCredentialSettingsTest extends DuskTestCase
{
    use DatabaseMigrations;

    protected User $landlord;

    protected PaymentConfiguration $paymentConfig;

    protected function setUp(): void
    {
        parent::setUp();

        $this->landlord = User::factory()->create([
            'role' => 'landlord',
            'email' => 'landlord@test.com',
            'password' => bcrypt('password'),
        ]);

        $this->paymentConfig = PaymentConfiguration::create([
            'landlord_id' => $this->landlord->id,
            'accepted_payment_methods' => ['cash', 'mobile_money'],
        ]);
    }

    public function test_landlord_can_save_mpesa_consumer_credentials(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->landlord)
                ->visit('/settings')
                ->waitForText('Payment Methods')
                ->click('@payment-methods-tab')
                ->waitFor('#mpesa_consumer_key')
                ->type('#mpesa_consumer_key', 'test_consumer_key_1234')
                ->type('#mpesa_consumer_secret', 'test_consumer_secret_5678')
                ->type('#mpesa_shortcode', '174379')
                ->press('Save Payment Methods')
                ->waitForText('Payment methods updated successfully');

            $this->paymentConfig->refresh();
            $this->assertEquals('test_consumer_key_1234', $this->paymentConfig->mpesa_consumer_key);
            $this->assertEquals('test_consumer_secret_5678', $this->paymentConfig->mpesa_consumer_secret);
            $this->assertEquals('174379', $this->paymentConfig->mpesa_shortcode);
        });
    }

    public function test_mpesa_credentials_show_last_4_chars_when_configured(): void
    {
        $this->paymentConfig->update([
            'mpesa_consumer_key' => 'my_secret_consumer_key_xxxx',
            'mpesa_consumer_secret' => 'my_secret_consumer_secret_yyyy',
        ]);

        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->landlord)
                ->visit('/settings')
                ->waitForText('Payment Methods')
                ->click('@payment-methods-tab')
                ->waitFor('#mpesa_consumer_key')
                ->assertSee('xxxx')
                ->assertSee('yyyy')
                ->assertDontSee('my_secret_consumer_key_xxxx')
                ->assertDontSee('my_secret_consumer_secret_yyyy');
        });
    }

    public function test_blank_credentials_preserve_existing_values(): void
    {
        $originalKey = 'original_consumer_key_abcd';
        $originalSecret = 'original_consumer_secret_efgh';

        $this->paymentConfig->update([
            'mpesa_consumer_key' => $originalKey,
            'mpesa_consumer_secret' => $originalSecret,
            'mpesa_shortcode' => '174379',
        ]);

        $this->browse(function (Browser $browser) use ($originalKey, $originalSecret) {
            $browser->loginAs($this->landlord)
                ->visit('/settings')
                ->waitForText('Payment Methods')
                ->click('@payment-methods-tab')
                ->waitFor('#mpesa_shortcode')
                ->clear('#mpesa_consumer_key')
                ->clear('#mpesa_consumer_secret')
                ->clear('#mpesa_shortcode')
                ->type('#mpesa_shortcode', '999999')
                ->press('Save Payment Methods')
                ->waitForText('Payment methods updated successfully');

            $this->paymentConfig->refresh();
            $this->assertEquals($originalKey, $this->paymentConfig->mpesa_consumer_key);
            $this->assertEquals($originalSecret, $this->paymentConfig->mpesa_consumer_secret);
            $this->assertEquals('999999', $this->paymentConfig->mpesa_shortcode);
        });
    }

    public function test_new_credentials_overwrite_existing_values(): void
    {
        $this->paymentConfig->update([
            'mpesa_consumer_key' => 'old_consumer_key',
            'mpesa_consumer_secret' => 'old_consumer_secret',
        ]);

        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->landlord)
                ->visit('/settings')
                ->waitForText('Payment Methods')
                ->click('@payment-methods-tab')
                ->waitFor('#mpesa_consumer_key')
                ->type('#mpesa_consumer_key', 'new_consumer_key_zzzz')
                ->type('#mpesa_consumer_secret', 'new_consumer_secret_wwww')
                ->press('Save Payment Methods')
                ->waitForText('Payment methods updated successfully');

            $this->paymentConfig->refresh();
            $this->assertEquals('new_consumer_key_zzzz', $this->paymentConfig->mpesa_consumer_key);
            $this->assertEquals('new_consumer_secret_wwww', $this->paymentConfig->mpesa_consumer_secret);
        });
    }

    public function test_credentials_are_encrypted_in_database(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->landlord)
                ->visit('/settings')
                ->waitForText('Payment Methods')
                ->click('@payment-methods-tab')
                ->waitFor('#mpesa_consumer_key')
                ->type('#mpesa_consumer_key', 'plaintext_key_1234')
                ->type('#mpesa_consumer_secret', 'plaintext_secret_5678')
                ->press('Save Payment Methods')
                ->waitForText('Payment methods updated successfully');

            $rawData = \DB::table('payment_configurations')
                ->where('id', $this->paymentConfig->id)
                ->first();

            $this->assertNotEquals('plaintext_key_1234', $rawData->mpesa_consumer_key);
            $this->assertNotEquals('plaintext_secret_5678', $rawData->mpesa_consumer_secret);

            $this->paymentConfig->refresh();
            $this->assertEquals('plaintext_key_1234', $this->paymentConfig->mpesa_consumer_key);
            $this->assertEquals('plaintext_secret_5678', $this->paymentConfig->mpesa_consumer_secret);
        });
    }
}
