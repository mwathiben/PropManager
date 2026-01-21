<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Traits\WithFinanceRendering;
use App\Http\Traits\WithLandlordScope;
use App\Models\InvoiceTemplate;
use App\Models\ReceiptTemplate;
use Inertia\Response;

class FinanceTemplateController extends Controller
{
    use WithFinanceRendering;
    use WithLandlordScope;

    public function index()
    {
        return redirect()->route('finances.templates.invoices');
    }

    public function invoices(): Response
    {
        $landlordId = $this->getLandlordId();
        $templates = InvoiceTemplate::where('landlord_id', $landlordId)
            ->orderBy('is_default', 'desc')
            ->orderBy('name')
            ->get();

        return $this->renderFinances('template-invoices', [
            'templates' => $templates,
            'designOptions' => [
                'classic' => 'Classic',
                'modern' => 'Modern',
                'minimal' => 'Minimal',
                'professional' => 'Professional',
            ],
        ]);
    }

    public function receipts(): Response
    {
        $landlordId = $this->getLandlordId();
        $receiptTemplates = ReceiptTemplate::where('landlord_id', $landlordId)
            ->orderBy('is_default', 'desc')
            ->orderBy('name')
            ->get();

        return $this->renderFinances('template-receipts', [
            'receiptTemplates' => $receiptTemplates,
            'designOptions' => [
                'classic' => 'Classic',
                'modern' => 'Modern',
                'minimal' => 'Minimal',
                'professional' => 'Professional',
            ],
        ]);
    }

    public function creditNotes(): Response
    {
        $landlordId = $this->getLandlordId();
        $templates = InvoiceTemplate::where('landlord_id', $landlordId)
            ->orderBy('is_default', 'desc')
            ->orderBy('name')
            ->get();

        return $this->renderFinances('template-credit-notes', [
            'templates' => $templates,
            'designOptions' => [
                'classic' => 'Classic',
                'modern' => 'Modern',
                'minimal' => 'Minimal',
                'professional' => 'Professional',
            ],
        ]);
    }
}
