<x-mail::message>
# Your Data Export is Ready

Hello {{ $userName }},

Your personal data export request has been processed. You can now download a copy of all your data stored in {{ config('app.name') }}.

<x-mail::button :url="$downloadUrl">
Download Your Data
</x-mail::button>

**Important Notes:**
- This download link will expire on **{{ $expiresAt }}**
- The export includes your personal information, lease history, invoices, payments, and uploaded documents
- The data is provided in machine-readable JSON format along with your original documents
- Please store this data securely and delete it when no longer needed

This export is provided in compliance with:
- **GDPR Article 20** (Right to Data Portability)
- **Kenya Data Protection Act 2019, Section 26** (Right of Access)

If you did not request this export or have any questions, please contact us immediately.

Thanks,<br>
{{ config('app.name') }} Team

<x-mail::subcopy>
For security reasons, we recommend downloading your data promptly and then deleting the export from our servers by not accessing the link after you have your copy.
</x-mail::subcopy>
</x-mail::message>
