<?php

namespace Database\Factories;

use App\Models\SubscriptionPlan;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class SubscriptionPlanFactory extends Factory
{
    protected $model = SubscriptionPlan::class;

    public function definition(): array
    {
        $name = fake()->unique()->randomElement(['Starter', 'Basic', 'Professional', 'Enterprise', 'Premium']);

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => fake()->sentence(),
            'price_monthly' => fake()->randomElement([0, 1500, 3000, 5000, 10000]),
            'price_yearly' => fake()->randomElement([0, 15000, 30000, 50000, 100000]),
            'currency' => 'KES',
            'max_properties' => fake()->randomElement([1, 3, 5, 10, 999]),
            'max_buildings' => fake()->randomElement([1, 5, 10, 50, 999]),
            'max_units' => fake()->randomElement([5, 20, 50, 200, 9999]),
            'max_caretakers' => fake()->randomElement([0, 1, 3, 5, 99]),
            'water_billing_enabled' => fake()->boolean(70),
            'ocr_enabled' => fake()->boolean(50),
            'reports_enabled' => fake()->boolean(70),
            'bulk_operations_enabled' => fake()->boolean(50),
            'document_storage_enabled' => true,
            'document_storage_mb' => fake()->randomElement([100, 500, 1000, 5000]),
            'email_notifications_enabled' => true,
            'sms_notifications_enabled' => fake()->boolean(50),
            'priority_support' => fake()->boolean(30),
            'is_active' => true,
            'sort_order' => fake()->numberBetween(1, 10),
        ];
    }

    public function active(): static
    {
        return $this->state(['is_active' => true]);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }

    public function free(): static
    {
        return $this->state([
            'name' => 'Free',
            'slug' => 'free',
            'price_monthly' => 0,
            'price_yearly' => 0,
            'max_properties' => 1,
            'max_buildings' => 1,
            'max_units' => 5,
            'max_caretakers' => 0,
            'water_billing_enabled' => false,
            'ocr_enabled' => false,
            'reports_enabled' => false,
            'bulk_operations_enabled' => false,
            'document_storage_mb' => 100,
            'sms_notifications_enabled' => false,
            'priority_support' => false,
            'sort_order' => 1,
        ]);
    }

    public function starter(): static
    {
        return $this->state([
            'name' => 'Starter',
            'slug' => 'starter',
            'price_monthly' => 1500,
            'price_yearly' => 15000,
            'max_properties' => 1,
            'max_buildings' => 3,
            'max_units' => 20,
            'max_caretakers' => 1,
            'water_billing_enabled' => true,
            'ocr_enabled' => false,
            'reports_enabled' => true,
            'bulk_operations_enabled' => false,
            'document_storage_mb' => 500,
            'sms_notifications_enabled' => false,
            'priority_support' => false,
            'sort_order' => 2,
        ]);
    }

    public function professional(): static
    {
        return $this->state([
            'name' => 'Professional',
            'slug' => 'professional',
            'price_monthly' => 5000,
            'price_yearly' => 50000,
            'max_properties' => 5,
            'max_buildings' => 20,
            'max_units' => 100,
            'max_caretakers' => 5,
            'water_billing_enabled' => true,
            'ocr_enabled' => true,
            'reports_enabled' => true,
            'bulk_operations_enabled' => true,
            'document_storage_mb' => 2000,
            'sms_notifications_enabled' => true,
            'priority_support' => false,
            'sort_order' => 3,
        ]);
    }

    public function enterprise(): static
    {
        return $this->state([
            'name' => 'Enterprise',
            'slug' => 'enterprise',
            'price_monthly' => 10000,
            'price_yearly' => 100000,
            'max_properties' => 999,
            'max_buildings' => 999,
            'max_units' => 9999,
            'max_caretakers' => 99,
            'water_billing_enabled' => true,
            'ocr_enabled' => true,
            'reports_enabled' => true,
            'bulk_operations_enabled' => true,
            'document_storage_mb' => 10000,
            'sms_notifications_enabled' => true,
            'priority_support' => true,
            'sort_order' => 4,
        ]);
    }

    public function withAllFeatures(): static
    {
        return $this->state([
            'water_billing_enabled' => true,
            'ocr_enabled' => true,
            'reports_enabled' => true,
            'bulk_operations_enabled' => true,
            'document_storage_enabled' => true,
            'email_notifications_enabled' => true,
            'sms_notifications_enabled' => true,
            'priority_support' => true,
        ]);
    }

    public function minimalFeatures(): static
    {
        return $this->state([
            'water_billing_enabled' => false,
            'ocr_enabled' => false,
            'reports_enabled' => false,
            'bulk_operations_enabled' => false,
            'document_storage_enabled' => true,
            'email_notifications_enabled' => true,
            'sms_notifications_enabled' => false,
            'priority_support' => false,
        ]);
    }
}
