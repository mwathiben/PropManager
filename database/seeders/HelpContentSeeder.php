<?php

namespace Database\Seeders;

use App\Models\Faq;
use App\Models\HelpArticle;
use Illuminate\Database\Seeder;

class HelpContentSeeder extends Seeder
{
    public function run(): void
    {
        // FAQs for all users
        $faqs = [
            // Getting Started
            [
                'question' => 'How do I get started with PropManager?',
                'answer' => 'After signing up, you\'ll be guided through an onboarding process where you can add your first property and building. From there, you can add units, invite caretakers, and start managing your tenants.',
                'category' => 'getting-started',
                'roles' => null,
                'order' => 1,
            ],
            [
                'question' => 'How do I add a new property?',
                'answer' => 'Navigate to the Properties page from the main menu and click "Add Property". Fill in the property details including name, address, and any additional information. You can then add buildings and units to this property.',
                'category' => 'getting-started',
                'roles' => ['landlord'],
                'order' => 2,
            ],
            [
                'question' => 'How do I add tenants to my units?',
                'answer' => 'Go to your building dashboard, find the vacant unit you want to rent out, and click on it. Select "Add Tenant" to create a new lease. Fill in the tenant details, lease terms, and rent amount to complete the process.',
                'category' => 'getting-started',
                'roles' => ['landlord'],
                'order' => 3,
            ],

            // Features
            [
                'question' => 'How does water billing work?',
                'answer' => 'Water billing allows you to track meter readings for each unit and automatically calculate water charges based on consumption. Set up your water rate per unit in Building Settings, then record readings periodically. The charges will be included in tenant invoices.',
                'category' => 'features',
                'roles' => ['landlord', 'caretaker'],
                'order' => 1,
            ],
            [
                'question' => 'How do I generate invoices?',
                'answer' => 'Invoices can be generated automatically by going to the Invoices page and clicking "Generate Invoices". This will create invoices for all active leases, including rent and any water charges from uninvoiced readings.',
                'category' => 'features',
                'roles' => ['landlord'],
                'order' => 2,
            ],
            [
                'question' => 'How do I invite a caretaker?',
                'answer' => 'Go to the Caretakers page from the main menu and click "Invite Caretaker". Enter their email address and select which property they\'ll manage. They\'ll receive an email invitation to create their account.',
                'category' => 'features',
                'roles' => ['landlord'],
                'order' => 3,
            ],
            [
                'question' => 'How do I submit a maintenance ticket?',
                'answer' => 'Click on "My Tickets" in the menu, then "Create Ticket". Describe the issue, select the priority level, and submit. Your landlord or caretaker will be notified and can track progress on the issue.',
                'category' => 'features',
                'roles' => ['tenant'],
                'order' => 4,
            ],
            [
                'question' => 'How do I record water readings?',
                'answer' => 'Go to Water Readings from the menu, select the building you\'re recording for, and enter the current meter readings for each unit. You can also upload photos of meters. Readings need to be approved by the landlord before being invoiced.',
                'category' => 'features',
                'roles' => ['caretaker'],
                'order' => 5,
            ],

            // Billing & Payments
            [
                'question' => 'How do I pay my rent?',
                'answer' => 'Go to "My Payments" to see your outstanding invoices. Click on an invoice to view details and payment options. You can pay via Paystack (card or mobile money) directly through the platform.',
                'category' => 'billing',
                'roles' => ['tenant'],
                'order' => 1,
            ],
            [
                'question' => 'What payment methods are accepted?',
                'answer' => 'PropManager supports payments via Paystack, which includes debit/credit cards and mobile money (M-Pesa). Landlords can also record manual payments (cash, bank transfer) on behalf of tenants.',
                'category' => 'billing',
                'roles' => null,
                'order' => 2,
            ],
            [
                'question' => 'How do I upgrade my subscription plan?',
                'answer' => 'Go to your profile menu (bottom left) and click "Subscription". You\'ll see your current plan and available upgrades. Click "Upgrade" on your preferred plan to proceed with payment.',
                'category' => 'billing',
                'roles' => ['landlord'],
                'order' => 3,
            ],

            // Troubleshooting
            [
                'question' => 'I forgot my password. How do I reset it?',
                'answer' => 'On the login page, click "Forgot Password". Enter your email address and you\'ll receive a password reset link. Click the link in the email to set a new password.',
                'category' => 'troubleshooting',
                'roles' => null,
                'order' => 1,
            ],
            [
                'question' => 'Why can\'t I add more properties/units?',
                'answer' => 'You may have reached your plan\'s limit. Check your subscription details to see your current usage. Consider upgrading to a higher plan for more capacity.',
                'category' => 'troubleshooting',
                'roles' => ['landlord'],
                'order' => 2,
            ],
            [
                'question' => 'My invoice seems incorrect. What should I do?',
                'answer' => 'Contact your landlord directly or submit a support ticket explaining the discrepancy. They can review the invoice details and make corrections if needed.',
                'category' => 'troubleshooting',
                'roles' => ['tenant'],
                'order' => 3,
            ],

            // Notifications
            [
                'question' => 'How do I set up notifications for my tenants?',
                'answer' => 'Go to Notifications in the main menu, then Settings > Channels. You can configure Email, SMS, WhatsApp, and Push notifications. We recommend using the Setup Wizard for a guided experience - click the "Setup Wizard" button at the top of the Channels tab.',
                'category' => 'notifications',
                'roles' => ['landlord'],
                'order' => 1,
            ],
            [
                'question' => 'Which notification channel should I use?',
                'answer' => 'Email is essential for all landlords as it\'s free and widely accessible. Add SMS for urgent rent reminders - it has higher open rates than email. WhatsApp is popular in regions where tenants prefer messaging apps. Push notifications are great for real-time alerts when tenants are using PropManager.',
                'category' => 'notifications',
                'roles' => ['landlord'],
                'order' => 2,
            ],
            [
                'question' => 'Do I need to configure all notification channels?',
                'answer' => 'No, you can start with just Email (which is recommended) and add other channels later. Each channel is independent, so you can enable only the ones that work best for your tenants. Many landlords use Email + SMS for the best coverage.',
                'category' => 'notifications',
                'roles' => ['landlord'],
                'order' => 3,
            ],
            [
                'question' => 'What SMTP settings should I use for Gmail?',
                'answer' => 'For Gmail use: Host: smtp.gmail.com, Port: 587, Encryption: TLS. Important: You\'ll need to generate an App Password in your Google Account security settings if you have 2-Factor Authentication enabled. Go to Google Account > Security > 2-Step Verification > App passwords, then generate a new password for PropManager.',
                'category' => 'notifications',
                'roles' => ['landlord'],
                'order' => 4,
            ],
            [
                'question' => 'Why are my emails going to spam?',
                'answer' => 'Emails may go to spam if: 1) Your domain lacks SPF/DKIM records - ask your domain provider to set these up, 2) You\'re using a free email provider like Gmail - consider a professional email service, 3) Your email content triggers spam filters - avoid excessive caps or spam trigger words. Consider using SendGrid or Mailgun for better deliverability.',
                'category' => 'notifications',
                'roles' => ['landlord'],
                'order' => 5,
            ],
            [
                'question' => 'Which SMS provider is better: Africa\'s Talking or Twilio?',
                'answer' => 'Africa\'s Talking is recommended for Kenya and East Africa - it\'s cheaper for local SMS (around KES 0.80-2 per SMS) and supports local integrations like M-Pesa. Twilio has better global coverage and is ideal if you have tenants in multiple countries, but costs more for African destinations.',
                'category' => 'notifications',
                'roles' => ['landlord'],
                'order' => 6,
            ],
            [
                'question' => 'How much does SMS cost?',
                'answer' => 'SMS costs vary by provider and destination. Africa\'s Talking typically charges KES 0.80-2 per SMS in Kenya. Twilio charges vary by country (check their pricing page). Both providers offer dashboards to monitor your spending and you can set budget alerts.',
                'category' => 'notifications',
                'roles' => ['landlord'],
                'order' => 7,
            ],
            [
                'question' => 'What is a Sender ID and do I need one?',
                'answer' => 'A Sender ID is the name that appears as the sender of your SMS (e.g., "PROPMANAGE" instead of a phone number). It\'s optional but makes your messages look more professional. Africa\'s Talking requires registration for custom Sender IDs in Kenya - apply through their dashboard.',
                'category' => 'notifications',
                'roles' => ['landlord'],
                'order' => 8,
            ],
            [
                'question' => 'How do I set up WhatsApp notifications?',
                'answer' => 'WhatsApp uses Twilio\'s WhatsApp API. You\'ll need a Twilio account first. For testing, use the Twilio WhatsApp Sandbox - go to Twilio Console > Messaging > Try it Out > WhatsApp. Tenants must send an opt-in code to the sandbox number before receiving messages. For production, apply for WhatsApp Business API approval.',
                'category' => 'notifications',
                'roles' => ['landlord'],
                'order' => 9,
            ],
            [
                'question' => 'Why do my tenants need to opt-in for WhatsApp?',
                'answer' => 'WhatsApp requires explicit opt-in for business messages to prevent spam. In sandbox/testing mode, each tenant must send a specific code to the sandbox number to enable receiving messages. In production with approved templates, this opt-in is typically handled during tenant onboarding.',
                'category' => 'notifications',
                'roles' => ['landlord'],
                'order' => 10,
            ],
            [
                'question' => 'What are VAPID keys?',
                'answer' => 'VAPID (Voluntary Application Server Identification) keys are cryptographic keys that identify your server to push notification services. They ensure your push notifications are secure and authenticated. PropManager generates these automatically - just click "Generate VAPID Keys" in the Push notification settings.',
                'category' => 'notifications',
                'roles' => ['landlord'],
                'order' => 11,
            ],
            [
                'question' => 'Why aren\'t my push notifications working on iPhone?',
                'answer' => 'Unfortunately, iOS Safari doesn\'t support Web Push notifications. This is a platform limitation from Apple that affects all web applications. Push notifications work on Android phones (Chrome, Firefox) and desktop browsers (Chrome, Firefox, Edge, Safari on Mac). For iPhone users, use Email or SMS instead.',
                'category' => 'notifications',
                'roles' => ['landlord'],
                'order' => 12,
            ],
            [
                'question' => 'How do I test if my notification settings are correct?',
                'answer' => 'After configuring each channel, click "Test Connection" or "Send Test". For Email, check your inbox for a test message. For SMS, you\'ll receive a test text. For Push, your browser should show a test notification. Also check Notifications > History to verify delivery status of all sent notifications.',
                'category' => 'notifications',
                'roles' => ['landlord'],
                'order' => 13,
            ],
            [
                'question' => 'My test connection failed. What should I do?',
                'answer' => 'Common causes: 1) Incorrect credentials - double-check API keys, passwords, and account IDs, 2) Account issues - ensure your provider account is active and has sufficient balance (for SMS), 3) Network issues - verify your server can reach the provider\'s API. Check the exact error message and consult the provider-specific setup guide in our Help Center.',
                'category' => 'notifications',
                'roles' => ['landlord'],
                'order' => 14,
            ],
        ];

        foreach ($faqs as $faq) {
            Faq::updateOrCreate(
                ['question' => $faq['question']],
                $faq
            );
        }

        // Help Articles
        $articles = [
            [
                'title' => 'Getting Started with PropManager',
                'slug' => 'getting-started',
                'content' => "# Welcome to PropManager\n\nPropManager is a comprehensive property management system designed to help landlords manage their properties, tenants, and finances efficiently.\n\n## First Steps\n\n1. **Add Your Property** - Start by adding your first property with its address and details.\n\n2. **Create Buildings** - Add buildings (wings or blocks) to your property.\n\n3. **Set Up Units** - Configure the units within each building with their details and target rents.\n\n4. **Add Tenants** - Create leases for your units and add tenant information.\n\n## Key Features\n\n- **Dashboard** - Get a visual overview of all your units and their status.\n- **Water Billing** - Track meter readings and automatically calculate water charges.\n- **Invoicing** - Generate and send professional invoices to tenants.\n- **Reports** - View financial reports and analytics.\n- **Document Storage** - Keep all your important documents organized.\n\n## Need Help?\n\nBrowse our FAQs or contact support if you need assistance.",
                'category' => 'getting-started',
                'roles' => ['landlord'],
                'order' => 1,
            ],
            [
                'title' => 'Managing Water Readings',
                'slug' => 'water-readings',
                'content' => "# Water Readings Guide\n\nPropManager makes it easy to track water consumption and bill tenants accurately.\n\n## Setting Up Water Billing\n\n1. Go to your building's Water Settings\n2. Enable water billing for the building\n3. Set your water rate per unit of consumption\n4. Optionally set a standing charge\n\n## Recording Readings\n\n1. Navigate to Water Readings\n2. Select the building\n3. Enter current meter readings for each unit\n4. Upload photos as proof (recommended)\n5. Submit for approval\n\n## Billing Process\n\nWater charges are automatically calculated based on:\n- Previous reading\n- Current reading\n- Rate per unit\n\nCharges are included in the next invoice generation.",
                'category' => 'features',
                'roles' => ['landlord', 'caretaker'],
                'order' => 2,
            ],
            [
                'title' => 'Understanding Your Invoice',
                'slug' => 'understanding-invoices',
                'content' => "# Understanding Your Invoice\n\nThis guide explains how to read and understand your monthly invoice.\n\n## Invoice Components\n\n### Rent\nYour monthly rent as specified in your lease agreement.\n\n### Water Charges\nBased on your water consumption for the billing period. Calculated as:\n`(Current Reading - Previous Reading) × Rate per Unit`\n\n### Arrears\nAny outstanding balance from previous invoices.\n\n### Total Due\nThe sum of all charges minus any payments or credits.\n\n## Payment Options\n\n- **Paystack** - Pay with card or mobile money\n- **Bank Transfer** - Transfer to the provided account\n- **Cash** - Pay directly to your landlord\n\n## Questions?\n\nIf you believe there's an error in your invoice, contact your landlord or submit a ticket.",
                'category' => 'billing',
                'roles' => ['tenant'],
                'order' => 3,
            ],
            [
                'title' => 'Caretaker Responsibilities',
                'slug' => 'caretaker-guide',
                'content' => "# Caretaker Guide\n\nAs a caretaker, you play a vital role in property management.\n\n## Your Responsibilities\n\n### Water Readings\n- Record meter readings regularly (usually monthly)\n- Take photos of meters as proof\n- Submit readings for landlord approval\n\n### Tickets\n- View and respond to tenant tickets\n- Update ticket status as issues are resolved\n- Communicate with tenants about maintenance\n\n### Building Oversight\n- Monitor building conditions\n- Report major issues to the landlord\n- Ensure common areas are maintained\n\n## Tips for Success\n\n1. Record readings on the same day each month for consistency\n2. Respond to tickets promptly\n3. Keep communication professional and helpful",
                'category' => 'features',
                'roles' => ['caretaker'],
                'order' => 4,
            ],

            // Notification Setup Articles
            [
                'title' => 'Notification Setup Overview',
                'slug' => 'notification-setup-overview',
                'content' => "# Setting Up Tenant Notifications\n\nPropManager supports multiple notification channels to keep your tenants informed about invoices, payments, and important updates.\n\n## Available Channels\n\n### Email\nThe most essential channel. Send invoices, payment confirmations, and reminders directly to tenant email addresses. Free to use with your own SMTP server.\n\n### SMS\nText messages for urgent notifications like overdue rent reminders. Higher open rates than email. Requires an SMS provider account (Africa's Talking or Twilio).\n\n### WhatsApp\nPopular messaging app integration via Twilio. Great for regions where WhatsApp is the primary communication method.\n\n### Push Notifications\nReal-time browser notifications when tenants are using PropManager. Free to use with VAPID keys.\n\n## Choosing the Right Channels\n\n**Recommended Setup:**\n1. **Email** - Essential for all landlords (invoices, receipts)\n2. **SMS** - Add for rent reminders (higher urgency)\n3. **WhatsApp** - Optional, based on tenant preferences\n4. **Push** - Optional, for engaged tenants using the platform\n\n## Getting Started\n\n1. Go to **Notifications** in the main menu\n2. Click **Settings** tab\n3. Select **Channels** to configure each notification method\n4. Use the **Setup Wizard** for guided configuration\n\n## Testing Your Setup\n\nAfter configuring each channel:\n1. Click **Test Connection** or **Send Test**\n2. Verify you receive the test notification\n3. Check **Notifications > History** to monitor delivery status",
                'category' => 'notifications',
                'roles' => ['landlord'],
                'order' => 1,
            ],
            [
                'title' => 'Email (SMTP) Setup Guide',
                'slug' => 'email-notifications-setup',
                'content' => "# Email Notifications Setup\n\nEmail is the foundation of tenant communications. This guide walks you through setting up SMTP email.\n\n## Prerequisites\n\n- Access to an email account or SMTP service\n- SMTP server credentials (host, port, username, password)\n\n## Step-by-Step Setup\n\n1. Go to **Notifications > Settings > Channels**\n2. Find the **Email** section and click **Configure**\n3. Enter your SMTP details\n\n## Provider-Specific Settings\n\n### Gmail\n```\nHost: smtp.gmail.com\nPort: 587\nEncryption: TLS\nUsername: your-email@gmail.com\nPassword: App Password (see below)\n```\n\n**Important:** Gmail requires an App Password if you have 2-Factor Authentication enabled:\n1. Go to Google Account > Security\n2. Enable 2-Step Verification if not already enabled\n3. Go to 2-Step Verification > App passwords\n4. Generate a new app password for \"Mail\"\n5. Use this 16-character password in PropManager\n\n### Outlook/Microsoft 365\n```\nHost: smtp.office365.com\nPort: 587\nEncryption: TLS\nUsername: your-email@outlook.com\nPassword: Your account password\n```\n\n### SendGrid (Recommended for high volume)\n```\nHost: smtp.sendgrid.net\nPort: 587\nEncryption: TLS\nUsername: apikey\nPassword: Your SendGrid API key\n```\n\n### Mailgun\n```\nHost: smtp.mailgun.org\nPort: 587\nEncryption: TLS\nUsername: Your Mailgun SMTP username\nPassword: Your Mailgun SMTP password\n```\n\n## From Address Configuration\n\n- **From Address:** The email address that appears as the sender\n- **From Name:** The display name (e.g., \"PropManager\" or your company name)\n\n## Testing\n\n1. After saving settings, click **Send Test Email**\n2. Check your inbox for the test message\n3. If it lands in spam, see our troubleshooting guide\n\n## Troubleshooting\n\n- **Authentication failed:** Double-check username and password\n- **Connection timeout:** Verify host and port are correct\n- **Emails going to spam:** Set up SPF/DKIM records for your domain",
                'category' => 'notifications',
                'roles' => ['landlord'],
                'order' => 2,
            ],
            [
                'title' => 'SMS Notifications Setup',
                'slug' => 'sms-notifications-setup',
                'content' => "# SMS Notifications Setup\n\nSMS notifications ensure urgent messages reach tenants instantly. PropManager supports two providers: Africa's Talking and Twilio.\n\n## Choosing a Provider\n\n### Africa's Talking\n**Best for:** Kenya and East Africa\n- Lower cost for local SMS (KES 0.80-2 per SMS)\n- Supports M-Pesa integration\n- Local sender IDs available\n- Website: africastalking.com\n\n### Twilio\n**Best for:** Global coverage\n- Works in 180+ countries\n- More expensive for African destinations\n- Better for international properties\n- Website: twilio.com\n\n## Africa's Talking Setup\n\n### 1. Create Account\n1. Go to [africastalking.com](https://africastalking.com)\n2. Sign up for a free account\n3. Verify your email and phone number\n\n### 2. Get API Credentials\n1. Log into the dashboard\n2. Go to **Settings > API Key**\n3. Generate a new API key\n4. Note your **Username** (shown in dashboard header)\n\n### 3. Configure in PropManager\n1. Go to **Notifications > Settings > Channels**\n2. Select **Africa's Talking** as SMS provider\n3. Enter:\n   - **Username:** Your AT username\n   - **API Key:** Your generated API key\n   - **Sender ID:** Optional (see below)\n\n### 4. Sender ID (Optional)\nA Sender ID shows your business name instead of a phone number. In Kenya:\n1. Apply through Africa's Talking dashboard\n2. Requires business registration documents\n3. Takes 2-5 business days to approve\n\n## Twilio Setup\n\n### 1. Create Account\n1. Go to [twilio.com](https://twilio.com)\n2. Sign up and verify your account\n3. Add credit or start with trial credits\n\n### 2. Get Credentials\n1. Go to Twilio Console\n2. Find **Account SID** and **Auth Token** on the dashboard\n3. Purchase a phone number (or use trial number)\n\n### 3. Configure in PropManager\n1. Go to **Notifications > Settings > Channels**\n2. Select **Twilio** as SMS provider\n3. Enter:\n   - **Account SID:** From Twilio console\n   - **Auth Token:** From Twilio console\n   - **Phone Number:** Your Twilio number (include country code)\n\n## Testing SMS\n\n1. After configuration, click **Send Test SMS**\n2. Enter your phone number\n3. You should receive a test message within seconds\n\n## Cost Management\n\n- Monitor usage in your provider's dashboard\n- Set up billing alerts to avoid unexpected charges\n- Africa's Talking: Add credits via M-Pesa or card\n- Twilio: Enable auto-recharge or add credits manually",
                'category' => 'notifications',
                'roles' => ['landlord'],
                'order' => 3,
            ],
            [
                'title' => 'WhatsApp Notifications Setup',
                'slug' => 'whatsapp-notifications-setup',
                'content' => "# WhatsApp Notifications Setup\n\nWhatsApp notifications use Twilio's WhatsApp Business API. This guide covers both sandbox (testing) and production setup.\n\n## Prerequisites\n\n- A Twilio account (see SMS setup guide)\n- Twilio phone number with WhatsApp enabled\n\n## Understanding WhatsApp Modes\n\n### Sandbox Mode (Testing)\n- Free to use\n- Each recipient must opt-in by sending a code\n- Messages expire after 24 hours of inactivity\n- Good for testing before going live\n\n### Production Mode\n- Requires WhatsApp Business API approval\n- No opt-in code needed (uses template messages)\n- Higher message limits\n- Requires Meta Business verification\n\n## Sandbox Setup (Recommended to Start)\n\n### 1. Enable WhatsApp Sandbox\n1. Go to Twilio Console\n2. Navigate to **Messaging > Try it Out > Send a WhatsApp Message**\n3. Note the sandbox number and join code\n\n### 2. Configure PropManager\n1. Go to **Notifications > Settings > Channels**\n2. In WhatsApp section, enter:\n   - **Account SID:** Your Twilio Account SID\n   - **Auth Token:** Your Twilio Auth Token\n   - **WhatsApp Number:** Sandbox number (format: whatsapp:+14155238886)\n\n### 3. Tenant Opt-In Process\nFor sandbox mode, each tenant must:\n1. Save the sandbox number to their contacts\n2. Send the join code (e.g., \"join [your-code]\")\n3. Receive confirmation of successful opt-in\n\n**Important:** Inform tenants about this one-time setup.\n\n## Production Setup\n\n### 1. Apply for WhatsApp Business API\n1. Go to Twilio Console > Messaging > Senders > WhatsApp Senders\n2. Click **New WhatsApp Sender**\n3. Complete Meta Business verification\n4. Wait for approval (can take 1-2 weeks)\n\n### 2. Create Message Templates\nWhatsApp requires pre-approved templates for business messages:\n- Invoice notifications\n- Payment reminders\n- Payment confirmations\n\nTemplates must be approved by Meta before use.\n\n### 3. Update PropManager Configuration\nAfter approval, update the WhatsApp number to your production number.\n\n## Best Practices\n\n1. **Respect the 24-hour window:** Only send messages within 24 hours of tenant's last message (sandbox limitation)\n2. **Use templates:** For production, always use approved templates\n3. **Provide opt-out:** Include unsubscribe instructions\n4. **Be concise:** WhatsApp messages should be short and actionable",
                'category' => 'notifications',
                'roles' => ['landlord'],
                'order' => 4,
            ],
            [
                'title' => 'Push Notifications Setup',
                'slug' => 'push-notifications-setup',
                'content' => "# Push Notifications Setup\n\nPush notifications deliver real-time alerts directly to tenant browsers. They're free to use and don't require any external service.\n\n## How Push Notifications Work\n\n1. Tenants visit PropManager and allow notifications\n2. Their browser subscribes to your push service\n3. When you send a notification, it appears on their device\n4. Works even when PropManager isn't open (desktop browsers)\n\n## VAPID Keys\n\nVAPID (Voluntary Application Server Identification) keys authenticate your server to push services. They ensure only you can send notifications to your subscribers.\n\n## Setup Steps\n\n### 1. Generate VAPID Keys\n1. Go to **Notifications > Settings > Channels**\n2. Find the **Push Notifications** section\n3. Click **Generate VAPID Keys**\n4. Keys are automatically saved and encrypted\n\n### 2. Configure VAPID Subject\nThe subject identifies who is sending notifications:\n- Format: `mailto:your-email@domain.com`\n- Use your support or admin email\n- Example: `mailto:support@yourcompany.com`\n\n### 3. Save Settings\nClick **Save** to enable push notifications.\n\n## Tenant Subscription\n\nWhen push is enabled, tenants will see a prompt to allow notifications:\n\n1. Tenant logs into PropManager\n2. Browser shows \"Allow notifications?\" prompt\n3. Tenant clicks **Allow**\n4. They're now subscribed to your notifications\n\n## Browser Compatibility\n\n### Fully Supported\n- Chrome (desktop & Android)\n- Firefox (desktop & Android)\n- Edge (desktop)\n- Safari (Mac only, macOS Ventura+)\n\n### Not Supported\n- Safari on iOS/iPhone (Apple limitation)\n- Any browser on iOS (all use Safari's engine)\n\n**Tip:** For iPhone users, use Email or SMS instead.\n\n## Testing Push Notifications\n\n1. After setup, click **Send Test Notification**\n2. Allow notifications if prompted\n3. You should see a test notification appear\n\n## Troubleshooting\n\n### Notification permission denied\n- Tenant blocked notifications\n- They need to reset permissions in browser settings\n\n### Notifications not appearing\n- Check browser supports push\n- Ensure HTTPS is enabled (required for push)\n- Verify VAPID keys are generated\n\n### Not working on iPhone\n- This is expected - iOS doesn't support web push\n- Recommend Email/SMS for iPhone users",
                'category' => 'notifications',
                'roles' => ['landlord'],
                'order' => 5,
            ],
            [
                'title' => 'Notification Troubleshooting',
                'slug' => 'notification-troubleshooting',
                'content' => "# Notification Troubleshooting Guide\n\nThis guide helps you diagnose and fix common notification issues.\n\n## Email Issues\n\n### Authentication Failed\n**Symptoms:** Test email fails with authentication error\n\n**Solutions:**\n1. Verify username and password are correct\n2. For Gmail: Use App Password, not regular password\n3. Check if account requires \"Less secure app access\"\n4. Try port 465 with SSL if 587 with TLS fails\n\n### Emails Going to Spam\n**Symptoms:** Emails arrive but land in spam folder\n\n**Solutions:**\n1. Set up SPF record for your domain\n2. Set up DKIM signing\n3. Use a professional email service (SendGrid, Mailgun)\n4. Avoid spam trigger words in subject lines\n5. Ensure From address matches your domain\n\n### Connection Timeout\n**Symptoms:** Test takes forever then fails\n\n**Solutions:**\n1. Verify SMTP host is correct\n2. Check port number (587 for TLS, 465 for SSL)\n3. Your server may be blocking outbound SMTP\n4. Try a different email provider\n\n## SMS Issues\n\n### Africa's Talking: Sandbox Mode\n**Symptoms:** SMS only works to registered numbers\n\n**Solution:** In sandbox, you must register recipient numbers first. Go to AT dashboard > Sandbox > Add test number.\n\n### Twilio: Trial Account Limitations\n**Symptoms:** Can only send to verified numbers\n\n**Solution:** Verify recipient numbers in Twilio Console, or upgrade to paid account.\n\n### Invalid Phone Number Format\n**Symptoms:** SMS fails with invalid number error\n\n**Solution:** Use international format with country code:\n- Kenya: +254712345678 (not 0712345678)\n- US: +15551234567\n\n### Insufficient Balance\n**Symptoms:** SMS fails with balance error\n\n**Solution:** Top up your provider account.\n\n## WhatsApp Issues\n\n### Tenant Not Receiving Messages\n**Symptoms:** WhatsApp messages fail silently\n\n**Solutions:**\n1. Verify tenant has opted into sandbox\n2. Check 24-hour window hasn't expired\n3. Confirm phone number format is correct\n4. Tenant must have WhatsApp installed\n\n### 24-Hour Window Expired\n**Symptoms:** Messages fail outside conversation window\n\n**Solution:** In sandbox, tenant must send a message first. In production, use approved template messages.\n\n## Push Notification Issues\n\n### VAPID Keys Error\n**Symptoms:** Push setup fails\n\n**Solutions:**\n1. Regenerate VAPID keys\n2. Clear browser cache and try again\n3. Check server logs for specific errors\n\n### Permission Denied\n**Symptoms:** Tenant can't receive notifications\n\n**Solutions:**\n1. Tenant needs to reset browser notification permissions\n2. Chrome: Settings > Privacy > Site Settings > Notifications\n3. Firefox: Settings > Privacy > Permissions > Notifications\n\n### Not Working on Mobile\n**Symptoms:** Push works on desktop but not mobile\n\n**Solutions:**\n1. iOS doesn't support web push - use SMS/Email\n2. Android: Ensure Chrome/Firefox is updated\n3. Check mobile browser allows notifications\n\n## General Troubleshooting\n\n### Check Notification History\n1. Go to **Notifications > History**\n2. Review delivery status of recent notifications\n3. Click on failed notifications to see error details\n\n### Test Connection Feature\nAlways use the **Test Connection** button after changing settings to verify configuration.\n\n### Getting Help\nIf issues persist:\n1. Note the exact error message\n2. Check provider dashboard for additional logs\n3. Contact support with details of your setup",
                'category' => 'notifications',
                'roles' => ['landlord'],
                'order' => 6,
            ],
        ];

        foreach ($articles as $article) {
            HelpArticle::updateOrCreate(
                ['slug' => $article['slug']],
                $article
            );
        }
    }
}
