<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AgreementSignatureMethod;
use App\Enums\AgreementSignatureStatus;
use App\Enums\DocumensoDocumentStatus;
use App\Traits\Auditable;
use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Slice-2 PR-2.3c: an owner's in-house e-signature request + evidence bundle.
 * The token is the single-use credential the owner signs through (no login —
 * PropertyOwner is a contact); the evidence columns capture who/when/where +
 * the signed snapshot hash + that an SMS OTP was verified, so the assent is
 * defensible. Landlord-scoped so a manager only ever sees its own.
 */
class AgreementSignature extends Model
{
    use Auditable;

    /** @use HasFactory<\Database\Factories\AgreementSignatureFactory> */
    use HasFactory;

    use TenantScope;

    protected $fillable = [
        'management_agreement_id',
        'landlord_id',
        'token',
        'status',
        'signer_name',
        'signer_email',
        'signer_phone',
        'content_hash',
        'otp_verified_at',
        'signed_at',
        'signed_ip',
        'signed_user_agent',
        'signing_method',
        'documenso_document_id',
        'documenso_envelope_id',
        'documenso_recipient_token',
        'documenso_status',
        'documenso_completed_at',
        'signed_pdf_path',
        'certificate_path',
        'sealed_pdf_sha256',
    ];

    protected function casts(): array
    {
        return [
            'status' => AgreementSignatureStatus::class,
            'signing_method' => AgreementSignatureMethod::class,
            'documenso_status' => DocumensoDocumentStatus::class,
            // A live signing-session bearer credential — encrypted at rest.
            'documenso_recipient_token' => 'encrypted',
            'otp_verified_at' => 'datetime',
            'signed_at' => 'datetime',
            'documenso_completed_at' => 'datetime',
            'documenso_document_id' => 'integer',
        ];
    }

    public function agreement(): BelongsTo
    {
        return $this->belongsTo(ManagementAgreement::class, 'management_agreement_id');
    }

    public function isPending(): bool
    {
        return $this->status === AgreementSignatureStatus::Pending;
    }

    /** A 64-char URL-safe single-use signing token. */
    public static function newToken(): string
    {
        return Str::random(64);
    }
}
