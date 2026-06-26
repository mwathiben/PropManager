<?php

declare(strict_types=1);

namespace App\Services\Agreements;

use App\Models\ManagementAgreement;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * Slice-2 PR-2.4b-ii: render an agreement's signed snapshot (rendered_body) to PDF
 * bytes for upload to Documenso, which seals it with the platform PKCS#12 cert.
 * rendered_body is plain text; the view preserves its line breaks.
 */
class AgreementPdfRenderer
{
    public function render(ManagementAgreement $agreement): string
    {
        return Pdf::loadView('agreements.pdf', ['agreement' => $agreement])
            ->setPaper('a4', 'portrait')
            ->output();
    }
}
