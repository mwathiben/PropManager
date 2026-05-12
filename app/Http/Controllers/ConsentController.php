<?php

namespace App\Http\Controllers;

use App\Models\Consent;
use App\Models\LegalDocument;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ConsentController extends Controller
{
    /**
     * Show the consent required page.
     */
    public function required(Request $request): Response
    {
        $missingConsents = Consent::getMissingConsents($request->user());

        $documents = [];
        foreach ($missingConsents as $missing) {
            $doc = LegalDocument::where('type', $missing['type'])
                ->where('version', $missing['version'])
                ->first();

            if ($doc) {
                $documents[] = [
                    'type' => $doc->type,
                    'type_name' => $doc->type_name,
                    'version' => $doc->version,
                    'title' => $doc->title,
                    'summary' => $doc->summary,
                    'effective_date' => $doc->effective_date->format('F j, Y'),
                ];
            }
        }

        return Inertia::render('Consent/Required', [
            'documents' => $documents,
        ]);
    }

    /**
     * Accept required consents.
     */
    public function accept(Request $request)
    {
        // VALID-11: enforce the type:version contract at the validator. Without
        // the regex, attackers could pass arbitrary strings — the explode()
        // below would silently destructure into null/garbage and we'd record
        // a Consent row with a null type.
        $validated = $request->validate([
            'consents' => 'required|array',
            'consents.*' => [
                'required',
                'string',
                'regex:/^(privacy_policy|terms_of_service|marketing|cookies):\d+\.\d+$/',
            ],
        ]);

        $user = $request->user();

        foreach ($validated['consents'] as $consentKey) {
            [$type, $version] = explode(':', $consentKey);

            Consent::record($user, $type, $version, [
                'accepted_via' => 'web',
                'page' => 'consent_required',
            ]);
        }

        return redirect()->intended(route('dashboard'))
            ->with('success', 'Thank you for accepting our terms.');
    }

    /**
     * View a legal document.
     */
    public function view(Request $request, string $type): Response
    {
        $document = LegalDocument::getActive($type);

        if (! $document) {
            abort(404, 'Document not found');
        }

        $userConsent = null;
        if ($request->user()) {
            $userConsent = Consent::where('user_id', $request->user()->id)
                ->where('consent_type', $type)
                ->where('is_granted', true)
                ->whereNull('withdrawn_at')
                ->latest()
                ->first();
        }

        return Inertia::render('Legal/Document', [
            'document' => [
                'type' => $document->type,
                'type_name' => $document->type_name,
                'version' => $document->version,
                'title' => $document->title,
                'content' => $document->content,
                'effective_date' => $document->effective_date->format('F j, Y'),
            ],
            'userConsent' => $userConsent ? [
                'version' => $userConsent->version,
                'granted_at' => $userConsent->granted_at->format('F j, Y'),
            ] : null,
        ]);
    }

    /**
     * Get user's consent history.
     */
    public function history(Request $request): Response
    {
        $consents = Consent::where('user_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn ($consent) => [
                'id' => $consent->id,
                'type' => $consent->consent_type,
                'version' => $consent->version,
                'is_active' => $consent->isActive(),
                'granted_at' => $consent->granted_at?->format('F j, Y g:i A'),
                'withdrawn_at' => $consent->withdrawn_at?->format('F j, Y g:i A'),
            ]);

        return Inertia::render('Settings/ConsentHistory', [
            'consents' => $consents,
        ]);
    }

    /**
     * Phase-13 DPA-1: generic consent withdrawal. Article 7(3) of GDPR
     * and Section 32 of the Kenya DPA require that withdrawing consent
     * be as easy as granting it. The withdrawMarketing path before this
     * commit only worked for one of the consent types the model
     * supports — all other types had no withdrawal route.
     *
     * Mandatory consents (terms + privacy) cannot be withdrawn here;
     * those are account-blocking and the user must use account
     * deletion instead. The validator pins this so a stray client
     * cannot reach into a blocking consent via this endpoint.
     */
    public function withdrawConsent(Request $request)
    {
        $validated = $request->validate([
            'type' => [
                'required',
                'string',
                'in:'.implode(',', self::WITHDRAWABLE_CONSENT_TYPES),
            ],
        ]);

        $consent = Consent::where('user_id', $request->user()->id)
            ->where('consent_type', $validated['type'])
            ->where('is_granted', true)
            ->whereNull('withdrawn_at')
            ->first();

        if ($consent) {
            $consent->withdraw();
        }

        return back()->with('success', 'Consent preferences updated.');
    }

    /**
     * Consent types that can be withdrawn via DPA-1 generic withdrawal.
     * Mandatory consents (terms_of_service, privacy_policy) are not
     * included — withdrawing those would block all account use, so the
     * user must instead initiate account deletion.
     */
    public const WITHDRAWABLE_CONSENT_TYPES = [
        Consent::TYPE_MARKETING,
        Consent::TYPE_DATA_PROCESSING,
        Consent::TYPE_THIRD_PARTY_SHARING,
        'cookies',
        'analytics',
        'profile_analytics',
    ];
}
