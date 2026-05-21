<?php

declare(strict_types=1);

return [
    'sla' => [
        'title' => '[TODO-ar] SLA overrides',
        'description' => '[TODO-ar] Customise response and resolution targets for your portfolio. Platform defaults apply when you have no matching override.',
        'flash' => [
            'created' => '[TODO-ar] SLA override saved.',
            'updated' => '[TODO-ar] SLA override updated.',
            'deleted' => '[TODO-ar] SLA override removed.',
        ],
    ],
    'vendor_onboarding' => [
        'subject' => '[TODO-ar] :landlord has added you as a vendor — please complete your profile',
        'heading' => '[TODO-ar] Welcome — finish your vendor profile',
        'greeting' => '[TODO-ar] Hello :name,',
        'body' => '[TODO-ar] :landlord has added you to their PropManager vendor list. Please confirm your phone and service area so they can route maintenance jobs to you.',
        'cta' => '[TODO-ar] Complete profile',
        'expiry_note' => '[TODO-ar] This link expires in 7 days. Contact the landlord directly if it lapses.',
        'signoff' => '[TODO-ar] Thank you, the :app team',
        'saved' => '[TODO-ar] Profile updated. Thank you.',
        'form' => [
            'title' => '[TODO-ar] Complete your vendor profile',
            'intro' => '[TODO-ar] Update your contact details and service area so the landlord can reach you for maintenance jobs.',
            'contact_person' => '[TODO-ar] Contact person',
            'phone' => '[TODO-ar] Phone',
            'address' => '[TODO-ar] Address',
            'notes' => '[TODO-ar] Specialties / service area',
            'submit' => '[TODO-ar] Save changes',
            'expired' => '[TODO-ar] This link has expired. Please ask the landlord to send a new invitation.',
        ],
    ],
    'vendor_assigned' => [
        'subject' => '[TODO-ar] You have been assigned to a maintenance ticket: :ticket',
        'heading' => '[TODO-ar] New maintenance assignment',
        'greeting' => '[TODO-ar] Hello :name,',
        'body' => '[TODO-ar] :landlord has assigned you to ticket ":title" (priority :priority). Please review the scope below and respond at your earliest convenience.',
        'scope_label' => '[TODO-ar] Scope of work',
        'note_label' => '[TODO-ar] Note from the landlord',
        'contact_note' => '[TODO-ar] Reply to this email or contact the landlord directly to confirm acceptance, provide a quote, or request additional information.',
        'signoff' => '[TODO-ar] Thank you, the :app team',
    ],
    'photos' => [
        'title' => 'صور الصيانة',
        'subtitle' => 'كل صورة تذكرة عبر عقاراتك',
        'filter_building' => 'المبنى',
        'filter_category' => 'الفئة',
        'filter_from' => 'من',
        'filter_to' => 'إلى',
        'filter_all' => 'الكل',
        'apply' => 'تطبيق',
        'reset' => 'إعادة تعيين',
        'export_pdf' => 'تصدير PDF',
        'empty' => 'لا توجد صور تطابق عوامل التصفية هذه.',
        'annotated' => 'مُعلَّقة',
        'view_ticket' => 'عرض التذكرة',
    ],
];
