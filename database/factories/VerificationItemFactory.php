<?php

namespace Database\Factories;

use App\Models\VerificationItem;
use App\Models\VerificationTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

class VerificationItemFactory extends Factory
{
    protected $model = VerificationItem::class;

    public function definition(): array
    {
        $documentType = fake()->randomElement([
            'tenant_id',
            'payslip',
            'reference_letter',
            'bank_statement',
            'utility_bill',
            'lease_agreement',
            'other',
        ]);

        return [
            'verification_template_id' => VerificationTemplate::factory(),
            'name' => $this->getNameForDocumentType($documentType),
            'document_type' => $documentType,
            'description' => fake()->optional(0.6)->sentence(),
            'is_required' => true,
            'sort_order' => fake()->numberBetween(1, 10),
        ];
    }

    private function getNameForDocumentType(string $documentType): string
    {
        return match ($documentType) {
            'tenant_id' => 'National ID / Passport',
            'payslip' => 'Recent Payslips (Last 3 months)',
            'reference_letter' => 'Previous Landlord Reference',
            'bank_statement' => 'Bank Statement (Last 3 months)',
            'utility_bill' => 'Utility Bill (Proof of Address)',
            'lease_agreement' => 'Signed Lease Agreement',
            default => 'Supporting Document',
        };
    }

    public function required(): static
    {
        return $this->state([
            'is_required' => true,
        ]);
    }

    public function optional(): static
    {
        return $this->state([
            'is_required' => false,
        ]);
    }

    public function tenantId(): static
    {
        return $this->state([
            'document_type' => 'tenant_id',
            'name' => 'National ID / Passport',
        ]);
    }

    public function payslip(): static
    {
        return $this->state([
            'document_type' => 'payslip',
            'name' => 'Recent Payslips (Last 3 months)',
        ]);
    }

    public function referenceLetter(): static
    {
        return $this->state([
            'document_type' => 'reference_letter',
            'name' => 'Previous Landlord Reference',
        ]);
    }

    public function bankStatement(): static
    {
        return $this->state([
            'document_type' => 'bank_statement',
            'name' => 'Bank Statement (Last 3 months)',
        ]);
    }

    public function utilityBill(): static
    {
        return $this->state([
            'document_type' => 'utility_bill',
            'name' => 'Utility Bill (Proof of Address)',
        ]);
    }

    public function sortOrder(int $order): static
    {
        return $this->state([
            'sort_order' => $order,
        ]);
    }

    public function forTemplate(VerificationTemplate $template): static
    {
        return $this->state([
            'verification_template_id' => $template->id,
        ]);
    }
}
