<x-mail::message>
# Welcome to {{ config('app.name') }}!

Hello {{ $landlord->name }},

Thank you for choosing {{ config('app.name') }} for your property management needs. We're excited to help you streamline your operations and manage your properties more efficiently.

## Quick Start Guide

Get your account set up in just a few steps:

### 1. Add Your Property
Start by creating your first property. This will be the foundation for all your buildings and units.

### 2. Configure Buildings
Add wings or blocks to your property and define the floor structure for each building.

### 3. Add Units
Set up individual units with rent amounts, meter numbers, and other details.

### 4. Invite Tenants
Send invitations to your tenants so they can access their portal and pay rent online.

### 5. Set Up Water Billing (Optional)
If you bill tenants for water usage, configure water meters and rates.

<x-mail::button :url="$onboardingUrl">
Start Setup Wizard
</x-mail::button>

## What You Can Do

With {{ config('app.name') }}, you can:

- **Manage Properties** - Track multiple properties, buildings, and units
- **Handle Tenants** - Send invitations, manage leases, and communicate easily
- **Generate Invoices** - Automatic monthly billing with rent and water charges
- **Accept Payments** - Online payments via M-Pesa, bank transfer, or card
- **Track Arrears** - Monitor overdue payments and send reminders
- **Water Billing** - Record meter readings and calculate charges automatically

## Need Help?

If you have any questions or need assistance, our support team is here to help.

<x-mail::button :url="$dashboardUrl" color="success">
Go to Dashboard
</x-mail::button>

Welcome aboard!

Thanks,<br>
{{ config('app.name') }} Team
</x-mail::message>
