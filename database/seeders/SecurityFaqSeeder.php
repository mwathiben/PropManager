<?php

namespace Database\Seeders;

use App\Models\Faq;
use Illuminate\Database\Seeder;

class SecurityFaqSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faqs = [
            [
                'question' => 'What is Two-Factor Authentication (2FA)?',
                'answer' => 'Two-Factor Authentication (2FA) adds an extra layer of security to your account. After entering your password, you\'ll need to enter a 6-digit code from an authenticator app on your phone. This means even if someone knows your password, they cannot access your account without your phone. We recommend enabling 2FA for all users, especially landlords who manage sensitive tenant and financial data.',
                'category' => 'security',
                'roles' => null, // All roles can see this
                'order' => 1,
                'is_published' => true,
            ],
            [
                'question' => 'How do I enable 2FA on my account?',
                'answer' => 'To enable Two-Factor Authentication: 1) Go to Settings from the main menu, 2) Click on "Two-Factor Authentication" in the Security section, 3) Click "Enable Two-Factor Authentication" and enter your password, 4) Scan the QR code with an authenticator app like Google Authenticator, Authy, or Microsoft Authenticator, 5) Enter the 6-digit code from your app to verify setup. Make sure to save your recovery codes in a safe place!',
                'category' => 'security',
                'roles' => null,
                'order' => 2,
                'is_published' => true,
            ],
            [
                'question' => 'What are recovery codes and how do I use them?',
                'answer' => 'Recovery codes are backup codes that let you access your account if you lose your phone or can\'t use your authenticator app. When you enable 2FA, you\'ll receive 8 recovery codes. Each code can only be used once. Store them securely (print them or save in a password manager). If you run low on codes, you can regenerate new ones from your Two-Factor Authentication settings. Never share your recovery codes with anyone.',
                'category' => 'security',
                'roles' => null,
                'order' => 3,
                'is_published' => true,
            ],
            [
                'question' => 'How do I export my personal data?',
                'answer' => 'Under GDPR and Kenya DPA, you have the right to receive a copy of your personal data. To export your data: 1) Go to Settings, 2) Click on "Privacy & Data", 3) Click "Request Data Export" or "Download Now" for immediate export. You\'ll receive a ZIP file containing your profile information, lease history, invoices, payments, and uploaded documents in a machine-readable format (JSON).',
                'category' => 'security',
                'roles' => null,
                'order' => 4,
                'is_published' => true,
            ],
            [
                'question' => 'How do I delete my account?',
                'answer' => 'To request account deletion: 1) Go to Settings → Privacy & Data, 2) Click "Request Account Deletion", 3) Optionally provide a reason, 4) Confirm your request. Your account will be scheduled for deletion after a 30-day grace period. During this time, you can cancel the deletion request if you change your mind. Note: You cannot delete your account if you have active leases or unpaid invoices.',
                'category' => 'security',
                'roles' => null,
                'order' => 5,
                'is_published' => true,
            ],
            [
                'question' => 'What data does PropManager collect?',
                'answer' => 'PropManager collects data necessary to provide property management services: Personal information (name, email, phone number), Property and unit details, Lease agreements and terms, Payment and invoice records, Water meter readings, Uploaded documents (ID copies, lease agreements). For landlords: bank details for payment processing. All sensitive data is encrypted. We do not sell your data to third parties. See our Privacy Policy for full details.',
                'category' => 'security',
                'roles' => null,
                'order' => 6,
                'is_published' => true,
            ],
            [
                'question' => 'How is my data protected?',
                'answer' => 'We take data security seriously: All data is encrypted in transit (HTTPS) and at rest, Sensitive fields like national IDs and bank details use additional encryption, Access controls ensure users only see their own data, Regular security audits and monitoring, Secure session management with automatic timeouts, Optional Two-Factor Authentication, All actions are logged for audit purposes. We comply with GDPR and Kenya Data Protection Act 2019 requirements.',
                'category' => 'security',
                'roles' => null,
                'order' => 7,
                'is_published' => true,
            ],
            [
                'question' => 'What are my rights under Kenya DPA?',
                'answer' => 'Under the Kenya Data Protection Act 2019, you have the right to: Access - Request a copy of your personal data, Rectification - Correct inaccurate data via your profile, Erasure - Request deletion of your data (Right to be Forgotten), Portability - Receive your data in a machine-readable format, Object - Opt out of marketing communications, Information - Know how your data is processed. To exercise these rights, go to Settings → Privacy & Data or contact support.',
                'category' => 'security',
                'roles' => null,
                'order' => 8,
                'is_published' => true,
            ],
        ];

        foreach ($faqs as $faq) {
            Faq::updateOrCreate(
                ['question' => $faq['question'], 'category' => $faq['category']],
                $faq
            );
        }

        $this->command->info('Security FAQs seeded successfully!');
    }
}
