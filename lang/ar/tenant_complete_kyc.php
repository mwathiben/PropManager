<?php

declare(strict_types=1);

/**
 * Phase-105+ i18n migration: tenant-side KYC completion page (Arabic).
 */
return [
    'page_title' => 'أكمل التحقق من هويتك (KYC)',
    'heading' => 'أكمل التحقق من هويتك (KYC)',
    'subtitle' => 'يرجى رفع المستندات المطلوبة للتحقق من هويتك',
    'progress_heading' => 'اكتمال التحقق من الهوية',
    'required_badge' => 'مطلوب',
    'document_rejected' => 'تم رفض المستند',
    'submitted_prefix' => 'تم الإرسال',
    'upload_new_document' => 'رفع مستند جديد',
    'upload_document' => 'رفع المستند',
    'click_to_upload' => 'انقر للرفع',
    'or_drag_and_drop' => 'أو اسحب وأفلت',
    'file_constraints' => 'PDF أو JPG أو PNG أو GIF (الحد الأقصى 10 ميجابايت)',
    'verified_and_approved' => 'تم التحقق من المستند والموافقة عليه',
    'awaiting_review' => 'في انتظار مراجعة المالك',
    'progress_count' => 'تم إرسال {completed} من أصل {total} مستندات مطلوبة',
    'upload_all_to_continue' => 'ارفع جميع المستندات المطلوبة للمتابعة',
    'uploading' => 'جارٍ الرفع...',
    'submit_documents' => 'إرسال المستندات',
    'about_heading' => 'حول التحقق من الهوية (KYC)',
    'about_body' => 'ستتم مراجعة مستنداتك بواسطة المالك. بمجرد الموافقة، ستحصل على وصول كامل إلى بوابة المستأجر. تأكد من أن المستندات واضحة ومقروءة.',
    'status_not_submitted' => 'لم يتم الإرسال',
    'errors' => [
        'file_too_large' => 'يجب ألا يتجاوز الملف 10 ميجابايت',
        'file_type_invalid' => 'يجب أن يكون الملف PDF أو JPG أو PNG أو GIF',
    ],
    'file_size' => [
        'bytes' => '{value} B',
        'kilobytes' => '{value} كيلوبايت',
        'megabytes' => '{value} ميجابايت',
    ],
];
