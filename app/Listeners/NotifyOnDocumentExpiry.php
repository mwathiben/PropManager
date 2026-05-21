<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\DocumentExpiryApproaching;
use App\Models\Lease;
use App\Models\Notification;
use App\Models\TenantKycSubmission;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Phase-82 DOC-REMINDERS-1: notify the landlord (and the tenant when the
 * document belongs to their lease/KYC) that a document is approaching expiry.
 * ShouldQueue + backoff (Phase-16 RESIL), mirrors NotifyOnLeaseRenewalApproaching.
 */
class NotifyOnDocumentExpiry implements ShouldQueue
{
    /** @var int */
    public $tries = 4;

    /** @var int[] */
    public $backoff = [30, 60, 300, 1800];

    public function __construct(private readonly NotificationService $notifications) {}

    public function handle(DocumentExpiryApproaching $event): void
    {
        $document = $event->document;
        $landlordId = (int) $document->landlord_id;
        if ($landlordId === 0) {
            return;
        }

        $expired = $event->daysRemaining < 0;
        $data = [
            'document_id' => $document->id,
            'days_remaining' => $event->daysRemaining,
            'url' => route('archive.hub', ['tab' => 'documents', 'expiry' => 'expiring']),
        ];

        $this->notifications->send(
            $landlordId,
            Notification::TYPE_DOCUMENT_EXPIRY,
            __('document.expiry_reminder.subject', ['title' => $document->title]),
            $expired
                ? __('document.expiry_reminder.body_expired', ['title' => $document->title])
                : __('document.expiry_reminder.body', ['title' => $document->title, 'days' => $event->daysRemaining]),
            $data,
            $landlordId,
        );

        // Notify the tenant when the document belongs to their lease or KYC.
        $tenantId = $this->resolveTenantId($document);
        if ($tenantId !== null && $tenantId !== $landlordId) {
            $this->notifications->send(
                $tenantId,
                Notification::TYPE_DOCUMENT_EXPIRY,
                __('document.expiry_reminder.subject', ['title' => $document->title]),
                $expired
                    ? __('document.expiry_reminder.body_expired', ['title' => $document->title])
                    : __('document.expiry_reminder.body', ['title' => $document->title, 'days' => $event->daysRemaining]),
                ['document_id' => $document->id, 'days_remaining' => $event->daysRemaining, 'url' => route('tenant.documents.index')],
                $landlordId,
            );
        }
    }

    private function resolveTenantId(\App\Models\Document $document): ?int
    {
        if ($document->documentable_type === Lease::class) {
            return $document->documentable?->tenant_id;
        }
        if ($document->documentable_type === TenantKycSubmission::class) {
            return $document->documentable?->user_id ?? $document->documentable?->tenant_id;
        }

        return null;
    }
}
