<?php

namespace Database\Seeders;

use App\Models\LegalDocument;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Seeds the current Platform Terms of Service and Privacy Policy as INACTIVE drafts.
 * Idempotent — safe to re-run.
 *
 * Documents seed with is_active = false. Activation is a deliberate, post-advocate-review
 * step: a super-admin must call LegalDocument::publish() (or flip is_active manually) only
 * after a Kenyan advocate has signed off on the content (see docs/legal-review-brief.md §A).
 *
 * The consent gate (EnsureLegalAcceptance) is inert until at least one active document
 * exists — seeding inactive docs therefore has no effect on the gate.
 */
class LegalDocumentSeeder extends Seeder
{
    public function run(): void
    {
        $effectiveDate = Carbon::now()->toDateString();

        foreach ($this->documents($effectiveDate) as $document) {
            LegalDocument::updateOrCreate(
                ['type' => $document['type'], 'version' => $document['version']],
                $document,
            );
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function documents(string $effectiveDate): array
    {
        return [
            [
                'type' => LegalDocument::TYPE_TERMS,
                'version' => '1.0',
                'title' => 'Terms of Service',
                'summary' => 'The terms governing your use of the PropManager platform.',
                'is_active' => false,
                'effective_date' => $effectiveDate,
                'created_by' => null,
                'content' => $this->terms(),
            ],
            [
                'type' => LegalDocument::TYPE_PRIVACY,
                'version' => '1.0',
                'title' => 'Privacy Policy',
                'summary' => 'How PropManager collects, uses, and protects your personal data.',
                'is_active' => false,
                'effective_date' => $effectiveDate,
                'created_by' => null,
                'content' => $this->privacy(),
            ],
        ];
    }

    private function terms(): string
    {
        return <<<'HTML'
        <p><strong>DRAFT — pending review by a Kenyan advocate before go-live.</strong></p>
        <h2>1. Acceptance</h2>
        <p>By using PropManager you agree to these Terms of Service. If you do not agree,
        you may not use the platform.</p>
        <h2>2. The platform is a neutral technology host</h2>
        <p>PropManager provides software that lets landlords, property managers, owners,
        caretakers and tenants record and execute their own arrangements. Agreements
        (including management agreements and tenancy agreements) are entered into
        <strong>solely between those parties</strong>. PropManager is <strong>not a party</strong>
        to any such agreement, is not an estate agent acting for any user, and does not hold
        users' funds on its own account.</p>
        <h2>3. No liability for breach by a party</h2>
        <p>PropManager is <strong>not liable</strong> for any party's breach of an agreement
        made through the platform, nor for the acts or omissions of any user. Disputes
        between users are between those users.</p>
        <h2>4. Informational, not legal advice</h2>
        <p>Clause templates and explanations provided in the platform are for convenience and
        information only and are <strong>not legal advice</strong>. You should seek independent
        legal advice before entering into any agreement.</p>
        <h2>5. Electronic execution</h2>
        <p>You consent to transact and sign electronically. Electronic records and signatures
        captured by the platform are intended to be valid and enforceable under the Kenya
        Information and Communications Act.</p>
        <h2>6. Governing law</h2>
        <p>These terms are governed by the laws of the Republic of Kenya.</p>
        HTML;
    }

    private function privacy(): string
    {
        return <<<'HTML'
        <p><strong>DRAFT — pending review by a Kenyan advocate before go-live.</strong></p>
        <h2>1. Scope</h2>
        <p>This policy explains how PropManager processes personal data in line with the
        Kenya Data Protection Act, 2019.</p>
        <h2>2. Data we process</h2>
        <p>Account and contact details, identification and financial information necessary to
        operate tenancies, payments and agreements, and records of your consents and
        signatures.</p>
        <h2>3. Lawful basis</h2>
        <p>We process data to perform the contract you are party to, to meet legal obligations,
        and — where stated — with your consent (which you may withdraw at any time).</p>
        <h2>4. Your rights</h2>
        <p>You may request access, rectification, erasure, objection and portability of your
        data, subject to the Act. Contact the platform to exercise these rights.</p>
        <h2>5. Retention &amp; security</h2>
        <p>We retain data only as long as necessary or as required by law, and apply technical
        and organisational safeguards including encryption of sensitive fields.</p>
        <h2>6. The Data Commissioner</h2>
        <p>You may lodge a complaint with the Office of the Data Protection Commissioner (ODPC).</p>
        HTML;
    }
}
