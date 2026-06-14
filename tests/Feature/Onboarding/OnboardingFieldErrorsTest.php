<?php

declare(strict_types=1);

namespace Tests\Feature\Onboarding;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The landlord onboarding wizard previously rendered no per-field validation
 * errors, so a 422 left the user on the step with no explanation. Every
 * validated field must now surface its server error through the shared
 * InputError component bound to form.errors.<field>. This is a source contract
 * because the regression is purely client-side Inertia error state the PHP
 * layer cannot observe.
 */
class OnboardingFieldErrorsTest extends TestCase
{
    private function source(): string
    {
        return (string) file_get_contents(resource_path('js/Pages/Onboarding/Index.vue'));
    }

    public function test_wizard_imports_the_input_error_component(): void
    {
        $this->assertStringContainsString(
            "import InputError from '@/Components/InputError.vue'",
            $this->source(),
            'The wizard must import InputError to render field-level validation errors.',
        );
    }

    #[DataProvider('validatedScalarFields')]
    public function test_wizard_binds_input_error_for_each_validated_field(string $field): void
    {
        $this->assertStringContainsString(
            "form.errors.{$field}\"",
            $this->source(),
            "Field '{$field}' must render <InputError :message=\"form.errors.{$field}\" /> so its 422 error is visible.",
        );
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function validatedScalarFields(): array
    {
        return [
            'management_context' => ['management_context'],
            'name' => ['name'],
            'property_name' => ['property_name'],
            'property_type' => ['property_type'],
            'floors' => ['floors'],
            'units_per_floor' => ['units_per_floor'],
            'default_rent' => ['default_rent'],
            'water_billing_type' => ['water_billing_type'],
            'accepted_payment_methods' => ['accepted_payment_methods'],
            'unit_id' => ['unit_id'],
            'tenant_email' => ['tenant_email'],
            'rent_amount' => ['rent_amount'],
            'deposit_amount' => ['deposit_amount'],
            'start_date' => ['start_date'],
        ];
    }

    public function test_wizard_binds_input_error_for_array_element_fields(): void
    {
        $source = $this->source();

        // Wings (step 4) and invitations (step 6) are array rows whose error keys
        // are dynamic, so they use bracket access with the loop index.
        $this->assertStringContainsString("form.errors['wings.' + index + '.name']", $source);
        $this->assertStringContainsString("form.errors['invitations.' + index + '.email']", $source);
    }
}
