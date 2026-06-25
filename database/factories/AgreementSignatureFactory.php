<?php

namespace Database\Factories;

use App\Enums\AgreementSignatureStatus;
use App\Models\AgreementSignature;
use App\Models\ManagementAgreement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AgreementSignature>
 */
class AgreementSignatureFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'management_agreement_id' => ManagementAgreement::factory(),
            // Derive from the agreement so the evidence row is same-scope by default.
            // Closure runs after management_agreement_id resolves (factory ordering).
            'landlord_id' => fn (array $attrs) => ManagementAgreement::find($attrs['management_agreement_id'])?->landlord_id,
            'token' => AgreementSignature::newToken(),
            'status' => AgreementSignatureStatus::Pending,
            'signer_name' => fake()->name(),
            'signer_email' => fake()->safeEmail(),
            'signer_phone' => '2547'.fake()->numerify('########'),
            'content_hash' => null,
            'otp_verified_at' => null,
            'signed_at' => null,
            'signed_ip' => null,
            'signed_user_agent' => null,
        ];
    }

    public function signed(): static
    {
        return $this->state(fn (array $attrs): array => [
            'status' => AgreementSignatureStatus::Signed,
            'content_hash' => hash('sha256', 'signed-snapshot'),
            'otp_verified_at' => now(),
            'signed_at' => now(),
            'signed_ip' => '127.0.0.1',
            'signed_user_agent' => 'Test/1.0',
        ]);
    }
}
