<?php

namespace App\Http\Traits;

use Inertia\Inertia;
use Inertia\Response;

trait WithFinanceRendering
{
    protected function renderFinances(string $tab, array $additionalProps = []): Response
    {
        $landlordId = $this->getLandlordId();

        $baseProps = [
            'activeTab' => $tab,
            'activeGroup' => $this->getActiveGroup($tab),
            'buildings' => $this->getBuildings($landlordId),
            'tabs' => $this->getTabsConfig(),
        ];

        return Inertia::render('Finances/Index', array_merge($baseProps, $additionalProps));
    }

    protected function getTabsConfig(): array
    {
        return [
            ['id' => 'overview', 'name' => 'Overview', 'route' => 'finances.overview'],
            [
                'id' => 'billing',
                'name' => 'Billing',
                'route' => 'finances.invoices',
                'subtabs' => [
                    ['id' => 'invoices', 'name' => 'Invoices', 'route' => 'finances.invoices'],
                    ['id' => 'payments', 'name' => 'Payments', 'route' => 'finances.payments'],
                ],
            ],
            ['id' => 'expenses', 'name' => 'Expenses', 'route' => 'finances.expenses'],
            [
                'id' => 'collections',
                'name' => 'Collections',
                'route' => 'finances.arrears',
                'subtabs' => [
                    ['id' => 'arrears', 'name' => 'Arrears', 'route' => 'finances.arrears'],
                    ['id' => 'late-fees', 'name' => 'Late Fees', 'route' => 'finances.late-fees'],
                    ['id' => 'deposits', 'name' => 'Deposits', 'route' => 'finances.deposits'],
                    ['id' => 'refunds', 'name' => 'Refunds', 'route' => 'finances.refunds'],
                ],
            ],
            ['id' => 'reconciliation', 'name' => 'Reconciliation', 'route' => 'finances.reconciliation'],
            ['id' => 'reports', 'name' => 'Reports', 'route' => 'finances.reports'],
            [
                'id' => 'templates',
                'name' => 'Templates',
                'route' => 'finances.templates.invoices',
                'subtabs' => [
                    ['id' => 'template-invoices', 'name' => 'Invoices', 'route' => 'finances.templates.invoices'],
                    ['id' => 'template-receipts', 'name' => 'Receipts', 'route' => 'finances.templates.receipts'],
                    ['id' => 'template-credit-notes', 'name' => 'Credit Notes', 'route' => 'finances.templates.credit-notes'],
                ],
            ],
            ['id' => 'settings', 'name' => 'Settings', 'route' => 'finances.settings'],
        ];
    }

    protected function getActiveGroup(string $tab): ?string
    {
        $groupMap = [
            'invoices' => 'billing',
            'payments' => 'billing',
            'arrears' => 'collections',
            'late-fees' => 'collections',
            'deposits' => 'collections',
            'refunds' => 'collections',
            'template-invoices' => 'templates',
            'template-receipts' => 'templates',
            'template-credit-notes' => 'templates',
        ];

        return $groupMap[$tab] ?? null;
    }
}
