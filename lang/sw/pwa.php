<?php

declare(strict_types=1);

return [
    'push' => [
        'permission_default_helper' => 'Bofya Washa msukumo ili kupokea arifa za kivinjari.',
        'permission_denied_helper' => 'Msukumo umezuiliwa katika kivinjari — uwashe kupitia mipangilio ya tovuti ili kujiandikisha.',
        'permission_granted_helper' => 'Arifa za msukumo zimewezeshwa kwa kivinjari hiki.',
        'subscribe_cta' => 'Washa msukumo',
        'unsubscribe_cta' => 'Zima msukumo',
        'manage_via_browser_helper' => 'Dhibiti katika mipangilio ya kivinjari',
        'push_disabled_until_permission_helper' => 'Washa arifa za kivinjari kwanza ili kubadilisha kituo cha msukumo.',
    ],
    'digest' => [
        'mail_subject' => 'Muhtasari wako wa wiki wa PropManager, :landlord',
        'weekly_summary_heading' => 'Muhtasari wako wa wiki',
        'greeting' => 'Habari :name,',
        'engagement_score_label' => 'Alama ya ushiriki',
        'delta_7d_suffix' => 'ikilinganishwa na wiki iliyopita',
        'usage_ratios_heading' => 'Matumizi ya mpango wakati huu',
        'feature_column' => 'Kipengele',
        'usage_column' => 'Yametumika',
        'limit_column' => 'Kikomo',
        'referrals_label' => 'Rufaa zilizothibitishwa (siku 30)',
        'current_plan_label' => 'Mpango wa sasa',
        'cta_open_dashboard' => 'Fungua dashibodi',
        'signature' => '— Timu ya :app',
        'opt_out_link_label' => 'Dhibiti mapendeleo ya arifa',
        'opt_out_link_helper' => 'zima muhtasari wa wiki bila kupoteza arifa za malipo',
    ],
    'gateway' => [
        'proration_failed_heading' => 'Mabadiliko ya mpango yamehifadhiwa ndani — usawazishaji wa lango unangoja',
        'dunning_inline_helper' => 'Paystack iliripoti malipo yaliyoshindwa; mfuatano wa ukumbusho umeanza.',
        'sync_pending_label' => 'Usawazishaji wa lango unangoja',
    ],
    'admin' => [
        'experiments_index_heading' => 'Majaribio',
        'create_experiment_cta' => 'Jaribio jipya',
        'conclude_experiment_cta' => 'Maliza jaribio',
        'significance_table_caption' => 'Jaribio la z la uwiano (alpha = 0.05)',
        'variant_weight_label' => 'Uzito',
        'variant_users_assigned_label' => 'Watumiaji waliopewa',
        'status_draft_label' => 'Rasimu',
        'status_running_label' => 'Linaendesha',
        'status_paused_label' => 'Limesimamishwa',
        'status_concluded_label' => 'Limemalizika',
    ],
    'retention' => [
        'cold_storage_rollover_heading' => 'Uhamishaji wa hifadhi baridi',
        'prune_window_label' => 'Dirisha la kuhifadhi (siku)',
        'archive_disk_label' => 'Diski ya kumbukumbu',
    ],
];
