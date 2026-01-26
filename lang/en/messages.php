<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Flash Messages
    |--------------------------------------------------------------------------
    |
    | User-facing success, error, and info messages displayed via session flash.
    | Organized by domain for maintainability.
    |
    */

    'invoice' => [
        'generated' => 'Generated :count invoices.',
        'status_updated' => 'Invoice status updated.',
        'deleted' => 'Invoice deleted.',
        'voided' => 'Invoice voided successfully.',
        'reissued' => 'Invoice reissued as draft.',
        'reminder_sent' => 'Payment reminder sent successfully.',
        'cannot_delete_paid' => 'Cannot delete paid invoices.',
        'cannot_remind_paid' => 'Cannot send reminder for paid invoices.',
        'cannot_void_status' => 'Only draft or sent invoices can be voided.',
        'cannot_void_with_payments' => 'Cannot void an invoice with payments. Refund payments first.',
        'cannot_reissue' => 'Only voided invoices can be reissued.',
    ],

    'payment' => [
        'recorded' => 'Payment of KES :amount recorded successfully.',
        'wallet_credited' => 'KES :amount credited to wallet.',
        'voided' => 'Payment voided successfully.',
        'receipt_sent' => 'Receipt sent successfully.',
        'verification_failed' => 'Payment verification failed.',
        'not_successful' => 'Payment was not successful.',
        'reference_not_found' => 'Payment reference not found.',
    ],

    'bulk' => [
        'rent_adjusted' => 'Rent adjusted for :count units.',
        'status_updated' => 'Status updated for :count units.',
        'leases_terminated' => 'Successfully terminated :count leases.',
        'leases_extended' => 'Successfully extended :count leases.',
        'deposits_adjusted' => 'Deposits adjusted for :count units.',
        'target_rent_updated' => 'Target rent updated for :count units.',
        'meters_updated' => 'Meter numbers updated for :count units.',
    ],

    'building' => [
        'created' => 'Building created successfully.',
        'updated' => 'Building updated successfully.',
        'deleted' => 'Building deleted successfully.',
    ],

    'unit' => [
        'created' => 'Unit created successfully.',
        'updated' => 'Unit updated successfully.',
        'deleted' => 'Unit deleted successfully.',
    ],

    'lease' => [
        'created' => 'Lease created successfully.',
        'updated' => 'Lease updated successfully.',
        'terminated' => 'Lease terminated successfully.',
        'extended' => 'Lease extended successfully.',
    ],

    'tenant' => [
        'invited' => 'Tenant invitation sent successfully.',
        'updated' => 'Tenant updated successfully.',
    ],

    'document' => [
        'uploaded' => 'Document uploaded successfully.',
        'deleted' => 'Document deleted successfully.',
    ],

    'notification' => [
        'sent' => 'Notification sent successfully.',
        'scheduled' => 'Notification scheduled successfully.',
    ],

];
