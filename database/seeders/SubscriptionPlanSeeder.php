<?php

namespace Database\Seeders;

use App\Models\SubscriptionPlan;
use Illuminate\Database\Seeder;

class SubscriptionPlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'name' => 'Free',
                'slug' => 'free',
                'description' => 'Perfect for getting started with property management',
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
                'document_storage_enabled' => false,
                'document_storage_mb' => 0,
                'email_notifications_enabled' => true,
                'sms_notifications_enabled' => false,
                'priority_support' => false,
                'sort_order' => 1,
            ],
            [
                'name' => 'Basic',
                'slug' => 'basic',
                'description' => 'For small landlords managing a few properties',
                'price_monthly' => 1500,
                'price_yearly' => 15000,
                'max_properties' => 2,
                'max_buildings' => 4,
                'max_units' => 20,
                'max_caretakers' => 2,
                'water_billing_enabled' => true,
                'ocr_enabled' => false,
                'reports_enabled' => true,
                'bulk_operations_enabled' => false,
                'document_storage_enabled' => true,
                'document_storage_mb' => 500,
                'email_notifications_enabled' => true,
                'sms_notifications_enabled' => false,
                'priority_support' => false,
                'sort_order' => 2,
            ],
            [
                'name' => 'Pro',
                'slug' => 'pro',
                'description' => 'For growing portfolios with advanced features',
                'price_monthly' => 3500,
                'price_yearly' => 35000,
                'max_properties' => 10,
                'max_buildings' => 20,
                'max_units' => 100,
                'max_caretakers' => 10,
                'water_billing_enabled' => true,
                'ocr_enabled' => true,
                'reports_enabled' => true,
                'bulk_operations_enabled' => true,
                'document_storage_enabled' => true,
                'document_storage_mb' => 2000,
                'email_notifications_enabled' => true,
                'sms_notifications_enabled' => true,
                'priority_support' => false,
                'sort_order' => 3,
            ],
            [
                'name' => 'Enterprise',
                'slug' => 'enterprise',
                'description' => 'Unlimited everything with priority support',
                'price_monthly' => 7500,
                'price_yearly' => 75000,
                'max_properties' => 999,
                'max_buildings' => 999,
                'max_units' => 9999,
                'max_caretakers' => 999,
                'water_billing_enabled' => true,
                'ocr_enabled' => true,
                'reports_enabled' => true,
                'bulk_operations_enabled' => true,
                'document_storage_enabled' => true,
                'document_storage_mb' => 10000,
                'email_notifications_enabled' => true,
                'sms_notifications_enabled' => true,
                'priority_support' => true,
                'sort_order' => 4,
            ],
        ];

        foreach ($plans as $plan) {
            SubscriptionPlan::updateOrCreate(
                ['slug' => $plan['slug']],
                $plan
            );
        }
    }
}
