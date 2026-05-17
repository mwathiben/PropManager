<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\ReportTemplate;
use Illuminate\Database\Seeder;

/**
 * Phase-50 TEMPLATE-MARKETPLACE-1: seeds 12 curated report templates
 * keyed on slug. Idempotent — re-runs safely via updateOrCreate.
 *
 * Each template.config conforms to ReportBuilderService::run shape
 * (table + fields + filters + group_by + sort_by + limit), validated
 * by SaaS-side runtime at first execution per landlord clone.
 */
class Phase50ReportTemplateSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->templates() as $sortOrder => $template) {
            ReportTemplate::query()->updateOrCreate(
                ['slug' => $template['slug']],
                array_merge($template, [
                    'is_active' => true,
                    'sort_order' => $sortOrder,
                ]),
            );
        }
    }

    /**
     * @return list<array{slug: string, name: string, category: string, description: string, config: array}>
     */
    private function templates(): array
    {
        return [
            [
                'slug' => 'rent-collection-by-month',
                'name' => 'Rent collection by month',
                'category' => 'finance',
                'description' => 'Sum of payments by month — primary cashflow indicator.',
                'config' => [
                    'table' => 'payments',
                    'fields' => ['payment.amount', 'payment.payment_date'],
                    'filters' => [],
                    'group_by' => ['payment.payment_date'],
                    'sort_by' => [['field' => 'payment.payment_date', 'direction' => 'desc']],
                    'limit' => 365,
                ],
            ],
            [
                'slug' => 'rent-collection-by-method',
                'name' => 'Rent collection by payment method',
                'category' => 'finance',
                'description' => 'Breakdown of payment volumes per channel (M-Pesa / bank / cash).',
                'config' => [
                    'table' => 'payments',
                    'fields' => ['payment.payment_method', 'payment.amount'],
                    'filters' => [],
                    'group_by' => ['payment.payment_method'],
                    'sort_by' => [['field' => 'payment.amount', 'direction' => 'desc']],
                    'limit' => 50,
                ],
            ],
            [
                'slug' => 'outstanding-invoices',
                'name' => 'Outstanding invoices',
                'category' => 'finance',
                'description' => 'All invoices not fully paid — drives collection workflows.',
                'config' => [
                    'table' => 'invoices',
                    'fields' => ['invoice.total_due', 'invoice.amount_paid', 'invoice.status', 'invoice.due_date'],
                    'filters' => [
                        ['field' => 'invoice.status', 'op' => '!=', 'value' => 'paid'],
                    ],
                    'group_by' => [],
                    'sort_by' => [['field' => 'invoice.due_date', 'direction' => 'asc']],
                    'limit' => 500,
                ],
            ],
            [
                'slug' => 'invoice-status-mix',
                'name' => 'Invoice status mix',
                'category' => 'finance',
                'description' => 'Distribution across paid / pending / overdue statuses.',
                'config' => [
                    'table' => 'invoices',
                    'fields' => ['invoice.status', 'invoice.total_due'],
                    'filters' => [],
                    'group_by' => ['invoice.status'],
                    'sort_by' => [['field' => 'invoice.total_due', 'direction' => 'desc']],
                    'limit' => 20,
                ],
            ],
            [
                'slug' => 'active-leases-roster',
                'name' => 'Active leases roster',
                'category' => 'occupancy',
                'description' => 'Currently active leases with rent amount + start date.',
                'config' => [
                    'table' => 'leases',
                    'fields' => ['lease.rent_amount', 'lease.start_date', 'lease.is_active'],
                    'filters' => [
                        ['field' => 'lease.is_active', 'op' => '=', 'value' => true],
                    ],
                    'group_by' => [],
                    'sort_by' => [['field' => 'lease.start_date', 'direction' => 'desc']],
                    'limit' => 500,
                ],
            ],
            [
                'slug' => 'lease-rent-distribution',
                'name' => 'Lease rent distribution',
                'category' => 'occupancy',
                'description' => 'Histogram of active-lease rent amounts.',
                'config' => [
                    'table' => 'leases',
                    'fields' => ['lease.rent_amount'],
                    'filters' => [
                        ['field' => 'lease.is_active', 'op' => '=', 'value' => true],
                    ],
                    'group_by' => ['lease.rent_amount'],
                    'sort_by' => [['field' => 'lease.rent_amount', 'direction' => 'asc']],
                    'limit' => 100,
                ],
            ],
            [
                'slug' => 'high-value-payments',
                'name' => 'High-value payments',
                'category' => 'finance',
                'description' => 'Payments above KES 50,000 — anti-fraud spot-check feed.',
                'config' => [
                    'table' => 'payments',
                    'fields' => ['payment.amount', 'payment.payment_date', 'payment.payment_method'],
                    'filters' => [
                        ['field' => 'payment.amount', 'op' => '>', 'value' => 50000],
                    ],
                    'group_by' => [],
                    'sort_by' => [['field' => 'payment.amount', 'direction' => 'desc']],
                    'limit' => 100,
                ],
            ],
            [
                'slug' => 'overdue-invoices-30d',
                'name' => 'Overdue invoices (30+ days)',
                'category' => 'tenant',
                'description' => 'Invoices past due — primary collections handoff list.',
                'config' => [
                    'table' => 'invoices',
                    'fields' => ['invoice.total_due', 'invoice.amount_paid', 'invoice.due_date', 'invoice.status'],
                    'filters' => [
                        ['field' => 'invoice.status', 'op' => '=', 'value' => 'overdue'],
                    ],
                    'group_by' => [],
                    'sort_by' => [['field' => 'invoice.due_date', 'direction' => 'asc']],
                    'limit' => 200,
                ],
            ],
            [
                'slug' => 'recent-payments',
                'name' => 'Recent payments (last 30 days)',
                'category' => 'finance',
                'description' => 'All payments captured in the most-recent 30-day window.',
                'config' => [
                    'table' => 'payments',
                    'fields' => ['payment.amount', 'payment.payment_date', 'payment.payment_method'],
                    'filters' => [
                        ['field' => 'payment.payment_date', 'op' => '>=', 'value' => now()->subDays(30)->toDateString()],
                    ],
                    'group_by' => [],
                    'sort_by' => [['field' => 'payment.payment_date', 'direction' => 'desc']],
                    'limit' => 500,
                ],
            ],
            [
                'slug' => 'payment-method-revenue-mix',
                'name' => 'Payment method revenue mix',
                'category' => 'growth',
                'description' => 'Channel-by-channel revenue split — informs gateway negotiations.',
                'config' => [
                    'table' => 'payments',
                    'fields' => ['payment.payment_method', 'payment.amount'],
                    'filters' => [],
                    'group_by' => ['payment.payment_method'],
                    'sort_by' => [['field' => 'payment.amount', 'direction' => 'desc']],
                    'limit' => 20,
                ],
            ],
            [
                'slug' => 'invoice-aging-by-due-date',
                'name' => 'Invoice aging by due date',
                'category' => 'finance',
                'description' => 'Invoices grouped by due-date bucket — aging-report substrate.',
                'config' => [
                    'table' => 'invoices',
                    'fields' => ['invoice.due_date', 'invoice.total_due', 'invoice.amount_paid'],
                    'filters' => [],
                    'group_by' => ['invoice.due_date'],
                    'sort_by' => [['field' => 'invoice.due_date', 'direction' => 'asc']],
                    'limit' => 365,
                ],
            ],
            [
                'slug' => 'lease-start-cohorts',
                'name' => 'Lease start cohorts',
                'category' => 'occupancy',
                'description' => 'Leases grouped by start date — cohort retention input.',
                'config' => [
                    'table' => 'leases',
                    'fields' => ['lease.start_date', 'lease.rent_amount'],
                    'filters' => [],
                    'group_by' => ['lease.start_date'],
                    'sort_by' => [['field' => 'lease.start_date', 'direction' => 'desc']],
                    'limit' => 365,
                ],
            ],
        ];
    }
}
