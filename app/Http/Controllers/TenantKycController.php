<?php

namespace App\Http\Controllers;

use App\Enums\KycSubmissionStatus;
use App\Http\Requests\Kyc\ReviewKycSubmissionRequest;
use App\Http\Requests\Kyc\SubmitKycDocumentsRequest;
use App\Models\Document;
use App\Models\KycRequirement;
use App\Models\TenantKycSubmission;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class TenantKycController extends Controller
{
    /**
     * Display the KYC completion form with dynamic requirements.
     */
    public function show(): Response
    {
        $user = auth()->user();
        $requirements = $this->getRequirementsForTenant($user);
        $existingSubmissions = $this->getExistingSubmissions($user);

        return Inertia::render('Tenant/CompleteKyc', [
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
                'mobile_number' => $user->mobile_number,
                'national_id' => $user->national_id,
                'emergency_contact_name' => $user->emergency_contact_name,
                'emergency_contact_phone' => $user->emergency_contact_phone,
                'profile_photo_url' => $user->profile_photo_url,
            ],
            'requirements' => $requirements->map(fn ($req) => [
                'id' => $req->id,
                'type' => $req->requirement_type,
                'label' => $req->label,
                'description' => $req->description,
                'is_required' => $req->is_required,
                'sort_order' => $req->sort_order,
            ])->values(),
            'submissions' => $existingSubmissions->map(fn ($sub) => [
                'id' => $sub->id,
                'requirement_id' => $sub->requirement_id,
                'status' => $sub->status->value,
                'status_label' => $sub->status->label(),
                'rejection_reason' => $sub->rejection_reason,
                'submitted_at' => $sub->submitted_at?->toISOString(),
                'document' => $sub->document ? [
                    'id' => $sub->document->id,
                    'file_name' => $sub->document->file_name,
                    'file_size_formatted' => $sub->document->file_size_formatted,
                ] : null,
                'value' => $sub->submission_value,
            ])->values(),
        ]);
    }

    /**
     * Process tenant KYC document submissions.
     */
    public function update(SubmitKycDocumentsRequest $request): RedirectResponse
    {
        $user = auth()->user();
        $validated = $request->validated();

        DB::transaction(function () use ($user, $validated) {
            foreach ($validated['submissions'] as $submissionData) {
                $requirementId = $submissionData['requirement_id'];
                $document = null;

                if (! empty($submissionData['file'])) {
                    $document = $this->storeDocument(
                        $user,
                        $submissionData['file'],
                        $requirementId
                    );
                }

                TenantKycSubmission::updateOrCreate(
                    [
                        'user_id' => $user->id,
                        'requirement_id' => $requirementId,
                    ],
                    [
                        'landlord_id' => $user->landlord_id,
                        'document_id' => $document?->id,
                        'submission_value' => $submissionData['value'] ?? null,
                        'status' => KycSubmissionStatus::Pending,
                        'rejection_reason' => null,
                        'reviewed_by' => null,
                        'reviewed_at' => null,
                        'submitted_at' => now(),
                    ]
                );
            }
        });

        if ($user->fresh()->hasCompletedKyc()) {
            return redirect()->route('dashboard')
                ->with('success', 'Profile completed successfully! Welcome to your dashboard.');
        }

        return back()->with('success', 'Documents submitted for review.');
    }

    /**
     * Review a tenant's KYC submission (landlord/caretaker).
     */
    public function review(
        ReviewKycSubmissionRequest $request,
        TenantKycSubmission $submission
    ): RedirectResponse {
        $validated = $request->validated();

        $submission->update([
            'status' => $validated['status'],
            'rejection_reason' => $validated['status'] === KycSubmissionStatus::Rejected->value
                ? $validated['rejection_reason']
                : null,
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
        ]);

        $statusLabel = KycSubmissionStatus::from($validated['status'])->label();

        return back()->with('success', "Submission {$statusLabel} successfully.");
    }

    /**
     * List pending KYC submissions for landlord review.
     */
    public function pendingReviews(): Response
    {
        $user = auth()->user();
        $landlordId = $user->isCaretaker() ? $user->landlord_id : $user->id;

        $submissions = TenantKycSubmission::with(['tenant', 'requirement', 'document'])
            ->where('landlord_id', $landlordId)
            ->pending()
            ->orderBy('submitted_at', 'asc')
            ->paginate(20);

        return Inertia::render('Kyc/PendingReviews', [
            'submissions' => $submissions->through(fn ($sub) => [
                'id' => $sub->id,
                'tenant' => [
                    'id' => $sub->tenant->id,
                    'name' => $sub->tenant->name,
                    'email' => $sub->tenant->email,
                ],
                'requirement' => [
                    'id' => $sub->requirement->id,
                    'type' => $sub->requirement->requirement_type,
                    'label' => $sub->requirement->label,
                ],
                'document' => $sub->document ? [
                    'id' => $sub->document->id,
                    'file_name' => $sub->document->file_name,
                    'file_size_formatted' => $sub->document->file_size_formatted,
                    'is_image' => $sub->document->isImage(),
                    'is_pdf' => $sub->document->isPdf(),
                ] : null,
                'value' => $sub->submission_value,
                'submitted_at' => $sub->submitted_at?->format('M d, Y H:i'),
            ]),
        ]);
    }

    /**
     * Get applicable KYC requirements for tenant.
     * Priority: Building-specific > Landlord-level > Global defaults
     */
    private function getRequirementsForTenant(User $tenant)
    {
        $lease = $tenant->lease;
        $buildingId = $lease?->unit?->building_id;
        $landlordId = $tenant->landlord_id;

        return KycRequirement::withoutGlobalScope('landlord')
            ->where(function ($query) use ($landlordId) {
                $query->where('landlord_id', $landlordId)
                    ->orWhereNull('landlord_id');
            })
            ->where(function ($query) use ($buildingId) {
                $query->where('building_id', $buildingId)
                    ->orWhereNull('building_id');
            })
            ->active()
            ->orderBy('sort_order')
            ->orderBy('label')
            ->get()
            ->groupBy('requirement_type')
            ->map(function ($group) {
                return $group->sortByDesc(fn ($r) => ($r->building_id !== null ? 2 : 0) +
                    ($r->landlord_id !== null ? 1 : 0)
                )->first();
            })
            ->values();
    }

    /**
     * Get tenant's existing submissions.
     */
    private function getExistingSubmissions(User $tenant)
    {
        return TenantKycSubmission::with('document')
            ->where('user_id', $tenant->id)
            ->get();
    }

    /**
     * Store uploaded document and create Document record.
     */
    private function storeDocument(User $user, $file, int $requirementId): Document
    {
        $requirement = KycRequirement::withoutGlobalScope('landlord')
            ->findOrFail($requirementId);

        $originalName = $file->getClientOriginalName();
        $sanitizedName = Str::slug(pathinfo($originalName, PATHINFO_FILENAME))
            .'.'.$file->getClientOriginalExtension();
        $fileName = time().'_'.$sanitizedName;

        $filePath = $file->storeAs(
            "documents/{$user->landlord_id}/kyc/{$user->id}",
            $fileName,
            'local'
        );

        return Document::create([
            'landlord_id' => $user->landlord_id,
            'documentable_id' => $user->id,
            'documentable_type' => User::class,
            'title' => $requirement->label,
            'file_name' => $originalName,
            'file_path' => $filePath,
            'mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
            'document_type' => $this->mapRequirementToDocumentType($requirement->requirement_type),
            'description' => "KYC submission for {$requirement->label}",
            'uploaded_by' => $user->id,
        ]);
    }

    /**
     * Map KYC requirement type to Document::DOCUMENT_TYPES.
     */
    private function mapRequirementToDocumentType(string $requirementType): string
    {
        return match ($requirementType) {
            'national_id' => 'tenant_id',
            'selfie' => 'other',
            'signed_lease' => 'lease_agreement',
            'proof_of_income' => 'payslip',
            'reference_letter' => 'reference_letter',
            'bank_statement' => 'bank_statement',
            default => 'other',
        };
    }
}
