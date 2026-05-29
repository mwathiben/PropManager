<?php

declare(strict_types=1);

/**
 * Phase-105+ i18n migration: tenant-side KYC completion page (post-onboarding).
 * Mirror sw/ar.
 */
return [
    'page_title' => 'Complete Your KYC',
    'heading' => 'Complete Your KYC',
    'subtitle' => 'Please upload the required documents to verify your identity',
    'progress_heading' => 'KYC Completion',
    'required_badge' => 'Required',
    'document_rejected' => 'Document Rejected',
    'submitted_prefix' => 'Submitted',
    'upload_new_document' => 'Upload New Document',
    'upload_document' => 'Upload Document',
    'click_to_upload' => 'Click to upload',
    'or_drag_and_drop' => 'or drag and drop',
    'file_constraints' => 'PDF, JPG, PNG or GIF (Max 10MB)',
    'verified_and_approved' => 'Document verified and approved',
    'awaiting_review' => 'Awaiting review by landlord',
    'progress_count' => '{completed} of {total} required documents submitted',
    'upload_all_to_continue' => 'Upload all required documents to continue',
    'uploading' => 'Uploading...',
    'submit_documents' => 'Submit Documents',
    'about_heading' => 'About KYC Verification',
    'about_body' => "Your documents will be reviewed by your landlord. Once approved, you'll have full access to your tenant portal. Make sure documents are clear and legible.",
    'status_not_submitted' => 'Not submitted',
    'errors' => [
        'file_too_large' => 'File must not exceed 10MB',
        'file_type_invalid' => 'File must be PDF, JPG, PNG, or GIF',
    ],
    'file_size' => [
        'bytes' => '{value} B',
        'kilobytes' => '{value} KB',
        'megabytes' => '{value} MB',
    ],
];
