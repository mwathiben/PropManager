<?php

namespace App\Http\Controllers;

use App\Http\Requests\Notification\StoreNotificationTemplateRequest;
use App\Http\Requests\Notification\UpdateNotificationTemplateRequest;
use App\Models\NotificationTemplate;
use App\Traits\HasBuildingFilter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Notification template CRUD + preview, split out of NotificationsController
 * (M2 decomposition). Verbatim move of the template actions; routes keep
 * their original names (notifications.templates.*) and point here.
 * Behaviour is locked by NotificationTemplateControllerTest.
 */
class NotificationTemplateController extends Controller
{
    use HasBuildingFilter;

    public function templates(Request $request): Response
    {
        $user = auth()->user();
        $landlordId = $user->effectiveScopeId();

        $templates = NotificationTemplate::where('landlord_id', $landlordId)
            ->orderBy('type')
            ->orderBy('name')
            ->get();

        $notificationTypes = $this->getNotificationTypes();
        $placeholders = [];
        foreach ($notificationTypes as $type) {
            $placeholders[$type['value']] = NotificationTemplate::getAllPlaceholders($type['value']);
        }

        return Inertia::render('Notifications/Index', [
            'activeTab' => 'templates',
            'templates' => $templates,
            'notificationTypes' => $notificationTypes,
            'placeholders' => $placeholders,
            'buildings' => $this->getBuildingsForFilter(),
            'tenants' => [],
            'notifications' => ['data' => []],
            'filters' => [],
        ]);
    }

    public function storeTemplate(StoreNotificationTemplateRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $user = auth()->user();
        $landlordId = $user->effectiveScopeId();

        NotificationTemplate::create([
            'landlord_id' => $landlordId,
            'name' => $validated['name'],
            'type' => $validated['type'],
            'subject' => $validated['subject'],
            'body' => $validated['body'],
            'available_placeholders' => array_keys(NotificationTemplate::getAllPlaceholders($validated['type'])),
            'is_active' => $validated['is_active'] ?? true,
            'is_default' => false,
        ]);

        return redirect()->back()->with('success', 'Template created successfully.');
    }

    public function updateTemplate(NotificationTemplate $template, UpdateNotificationTemplateRequest $request): RedirectResponse
    {
        $this->authorizeTemplate($template);

        $validated = $request->validated();

        $template->update($validated);

        return redirect()->back()->with('success', 'Template updated successfully.');
    }

    public function destroyTemplate(NotificationTemplate $template): RedirectResponse
    {
        $this->authorizeTemplate($template);

        if ($template->is_default) {
            return redirect()->back()->with('error', 'Cannot delete default templates.');
        }

        $template->delete();

        return redirect()->back()->with('success', 'Template deleted successfully.');
    }

    /**
     * Preview a template with sample data
     */
    public function previewTemplate(NotificationTemplate $template, Request $request): JsonResponse
    {
        $this->authorizeTemplate($template);

        $sampleContext = [
            'tenant_name' => 'John Doe',
            'tenant_email' => 'john@example.com',
            'unit_number' => 'A101',
            'building_name' => 'Sunset Apartments',
            'landlord_name' => auth()->user()->name,
            'property_name' => 'Sunset Heights',
            'current_date' => now()->format('F j, Y'),
            'rent_amount' => '25,000.00',
            'due_date' => now()->addDays(7)->format('F j, Y'),
            'days_until_due' => '7',
            'arrears_amount' => '50,000.00',
            'days_overdue' => '14',
            'invoice_number' => 'INV-2024-001',
            'total_amount' => '27,500.00',
        ];

        $rendered = $template->render($sampleContext);

        return response()->json([
            'subject' => $rendered['subject'],
            'body' => $rendered['body'],
        ]);
    }

    private function authorizeTemplate(NotificationTemplate $template): void
    {
        $user = auth()->user();
        $landlordId = $user->effectiveScopeId();

        if ($template->landlord_id !== $landlordId) {
            abort(403, 'Unauthorized');
        }
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function getNotificationTypes(): array
    {
        return [
            ['value' => 'rent_reminder', 'label' => 'Rent Reminder'],
            ['value' => 'arrears_notice', 'label' => 'Arrears Notice'],
            ['value' => 'invoice', 'label' => 'Invoice'],
            ['value' => 'receipt', 'label' => 'Receipt'],
            ['value' => 'rent_hike', 'label' => 'Rent Hike'],
            ['value' => 'lease_expiry', 'label' => 'Lease Expiry'],
            ['value' => 'lease_renewal', 'label' => 'Lease Renewal'],
            ['value' => 'maintenance_notice', 'label' => 'Maintenance Notice'],
            ['value' => 'general', 'label' => 'General'],
            ['value' => 'eviction_notice', 'label' => 'Eviction Notice'],
        ];
    }
}
