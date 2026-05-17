<?php

declare(strict_types=1);

return [
    'invoice' => [
        'generated' => '[TODO-ar] Generated :count invoices.',
        'status_updated' => '[TODO-ar] Invoice status updated.',
        'deleted' => '[TODO-ar] Invoice deleted.',
        'voided' => '[TODO-ar] Invoice voided successfully.',
        'reissued' => '[TODO-ar] Invoice reissued as draft.',
        'reminder_sent' => '[TODO-ar] Payment reminder sent successfully.',
        'cannot_delete_paid' => '[TODO-ar] Cannot delete paid invoices.',
        'cannot_remind_paid' => '[TODO-ar] Cannot send reminder for paid invoices.',
        'cannot_void_status' => '[TODO-ar] Only draft or sent invoices can be voided.',
        'cannot_void_with_payments' => '[TODO-ar] Cannot void an invoice with payments. Refund payments first.',
        'cannot_reissue' => '[TODO-ar] Only voided invoices can be reissued.',
    ],
    'payment' => [
        'recorded' => '[TODO-ar] Payment of KES :amount recorded successfully.',
        'wallet_credited' => '[TODO-ar] KES :amount credited to wallet.',
        'voided' => '[TODO-ar] Payment voided successfully.',
        'receipt_sent' => '[TODO-ar] Receipt sent successfully.',
        'verification_failed' => '[TODO-ar] Payment verification failed.',
        'not_successful' => '[TODO-ar] Payment was not successful.',
        'reference_not_found' => '[TODO-ar] Payment reference not found.',
    ],
    'bulk' => [
        'rent_adjusted' => '[TODO-ar] Rent adjusted for :count units.',
        'status_updated' => '[TODO-ar] Status updated for :count units.',
        'leases_terminated' => '[TODO-ar] Successfully terminated :count leases.',
        'leases_extended' => '[TODO-ar] Successfully extended :count leases.',
        'deposits_adjusted' => '[TODO-ar] Deposits adjusted for :count units.',
        'target_rent_updated' => '[TODO-ar] Target rent updated for :count units.',
        'meters_updated' => '[TODO-ar] Meter numbers updated for :count units.',
    ],
    'building' => [
        'created' => '[TODO-ar] Building created successfully.',
        'updated' => '[TODO-ar] Building updated successfully.',
        'deleted' => '[TODO-ar] Building deleted successfully.',
    ],
    'unit' => [
        'created' => '[TODO-ar] Unit created successfully.',
        'updated' => '[TODO-ar] Unit updated successfully.',
        'deleted' => '[TODO-ar] Unit deleted successfully.',
    ],
    'lease' => [
        'created' => '[TODO-ar] Lease created successfully.',
        'updated' => '[TODO-ar] Lease updated successfully.',
        'terminated' => '[TODO-ar] Lease terminated successfully.',
        'extended' => '[TODO-ar] Lease extended successfully.',
    ],
    'tenant' => [
        'invited' => '[TODO-ar] Tenant invitation sent successfully.',
        'updated' => '[TODO-ar] Tenant updated successfully.',
    ],
    'document' => [
        'uploaded' => '[TODO-ar] Document uploaded successfully.',
        'deleted' => '[TODO-ar] Document deleted successfully.',
    ],
    'notification' => [
        'sent' => '[TODO-ar] Notification sent successfully.',
        'scheduled' => '[TODO-ar] Notification scheduled successfully.',
    ],
];
