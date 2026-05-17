<?php

declare(strict_types=1);

/*
 * Phase-29 [WORKFLOW-AUTOMATION] i18n keys for landlord-driven
 * workflow notifications. Swahili parity enforced by Phase24CiTest —
 * key ORDER must match lang/en/workflow.php exactly.
 */

return [
    'rent_reminder' => [
        'subject' => 'Ukumbusho wa kodi kwa ankara :number',
        'body_before' => 'Malipo yako ya kodi ya KES :amount kwenye ankara :number yanadaiwa baada ya siku :days.',
        'body_due_today' => 'Malipo yako ya kodi ya KES :amount kwenye ankara :number yanadaiwa leo.',
        'body_after' => 'Malipo yako ya kodi ya KES :amount kwenye ankara :number yamechelewa kwa siku :days. Tafadhali lipa haraka iwezekanavyo ili kuepuka adhabu.',
    ],
    'vacancy' => [
        'task_title' => 'Tangaza nyumba :number kwa mpangaji mpya',
        'task_description' => 'Nyumba :number sasa haina mpangaji. Sasisha tangazo, picha na vigezo vya uchunguzi.',
    ],
    'occupancy' => [
        'breach_subject' => 'Ujazo wa wapangaji uko chini ya lengo — :name',
        'breach_body' => 'Jengo :name liko na ujazo wa :current%, chini ya lengo la :target%. Fikiria kuangalia bei au matangazo.',
    ],
    'late_fee' => [
        'sms_subject' => 'Haraka: ankara :number imechelewa',
        'sms_body' => 'Ankara :number yenye salio KES :amount imechelewa. Tafadhali lipa leo ili kuepuka hatua zaidi.',
        'task_title' => 'Mpigie :tenant simu kuhusu ankara :number iliyochelewa',
        'task_description' => 'Mpangaji hajalipa ankara :number licha ya vikumbusho. Pigia simu ili kuthibitisha nia ya malipo.',
        'eviction_draft_body' => "ILANI KWA MPANGAJI :tenant\n\nKodi chini ya ankara :number (iliyodaiwa :due_date) haijalipwa kwa zaidi ya siku 30. Jumla ya kiasi kinachodaiwa ni KES :amount.\n\nIsipokuwa malipo yatapokelewa ndani ya siku saba (7) kutoka tarehe ya ilani hii, mwenye nyumba anaweza kuanza taratibu chini ya sheria ya upangaji ya Kenya kwa ajili ya kurudisha jumba na malipo yaliyosalia.\n\nHii ni RASIMU inayosubiri uhakiki wa mwenye nyumba.",
    ],
    'lease_renewal' => [
        'subject' => 'Kuongezewa kwa mkataba — siku :days zinabaki',
        'body' => 'Mkataba wako unakwisha tarehe :end_date — siku :days kutoka leo. Tafadhali angalia masharti mapya.',
        'proposed' => 'Masharti ya kuongezewa yamependekezwa. Mpangaji atataarifiwa.',
        'confirmed' => 'Kuongezewa kumethibitishwa. Tarehe ya mwisho na kodi zimesasishwa.',
        'tenant_accepted' => 'Umekubali masharti ya kuongezewa. Inasubiri uthibitisho wa mwenye nyumba.',
        'tenant_rejected' => 'Umekataa masharti ya kuongezewa. Mwenye nyumba atataarifiwa.',
        'tenant_countered' => 'Pendekezo lako jipya limetumwa kwa mwenye nyumba kwa ukaguzi.',
        'counter_accepted' => 'Umekubali pendekezo la mpangaji. Kuongezewa sasa kiko katika hali ya kukubaliwa.',
        'counter_rejected' => 'Umekataa pendekezo la mpangaji. Kuongezewa kumefungwa.',
        'counter_re_proposed' => 'Umependekeza upya na masharti mapya. Mpangaji atataarifiwa.',
    ],
    'payment_plan_mod' => [
        'proposed' => 'Ombi la marekebisho limewasilishwa. Inasubiri ukaguzi wa mwenye nyumba.',
        'approved' => 'Marekebisho yamekubaliwa. Ratiba mpya ya malipo iko tayari.',
        'rejected' => 'Marekebisho yamekataliwa. Ratiba ya awali bado iko tayari.',
    ],
    'payment_plan' => [
        'approved' => 'Mpango wa malipo umekubaliwa. Mpangaji amejulishwa.',
        'rejected' => 'Mpango wa malipo umekataliwa. Mpangaji amejulishwa.',
        'approved_subject' => 'Mpango wako wa malipo umekubaliwa',
        'approved_body' => 'Mwenye nyumba amekubali ombi lako la mpango wa malipo. Tarehe na kiasi cha awamu sasa ziko mbioni.',
        'rejected_subject' => 'Mpango wako wa malipo umekataliwa',
        'rejected_body' => 'Mwenye nyumba amekataa ombi la mpango wa malipo. Sababu: :reason',
    ],
    'deposit_refund' => [
        'approved' => 'Kurudisha amana kumekubaliwa. Mpangaji amejulishwa.',
        'rejected' => 'Kurudisha amana kumekataliwa. Mpangaji amejulishwa.',
        'paid' => 'Kurudisha amana kumewekwa alama ya kulipwa. Mpangaji amejulishwa.',
        'approved_subject' => 'Kurudisha amana yako kumekubaliwa',
        'approved_body' => 'Kurudisha amana yako ya KES :amount kumekubaliwa na kutaprocessitiwa hivi karibuni.',
        'rejected_subject' => 'Kurudisha amana yako kumekataliwa',
        'rejected_body' => 'Ombi lako la kurudisha amana limekataliwa. Sababu: :reason',
        'paid_subject' => 'Kurudisha amana yako kumelipwa',
        'paid_body' => 'Kurudisha amana yako kumelipwa. Kumbukumbu ya malipo: :reference',
        'b2c_queued' => 'Marejesho yamepangwa kwa malipo ya M-Pesa B2C.',
        'b2c_sent' => 'Malipo ya M-Pesa B2C yameanzishwa. Tunasubiri uthibitisho.',
        'b2c_succeeded' => 'Malipo ya M-Pesa B2C yamefanikiwa.',
        'b2c_failed' => 'Malipo ya M-Pesa B2C yameshindwa.',
        'b2c_timed_out' => 'Malipo ya M-Pesa B2C yamechelewa — usawazishaji unasubiriwa.',
    ],
];
