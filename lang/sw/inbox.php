<?php

declare(strict_types=1);

return [
    'thread_created' => 'Mfululizo wa ujumbe umeundwa.',
    'message_sent' => 'Ujumbe umetumwa.',
    'thread_locked' => 'Mfululizo umefungwa.',
    'thread_archived' => 'Mfululizo umehifadhiwa.',
    'seen' => [
        'label' => 'Imeonekana',
        'mark_all' => 'Weka zote zimesomwa',
    ],
    'presence' => [
        'online' => 'Mtandaoni',
        'typing' => '{name} anaandika… | {name} wanaandika…',
    ],
    'search' => [
        'placeholder' => 'Tafuta ujumbe…',
        'title' => 'Tafuta ujumbe',
        'empty' => 'Andika herufi 3 au zaidi kutafuta.',
        'no_results' => 'Hakuna ujumbe unaolingana na "{term}".',
        'in_thread' => 'katika {title}',
    ],
    'scan' => [
        'hint' => 'Viambatisho hukaguliwa kabla ya kutumwa.',
        'blocked' => 'Kiambatisho kimezuiwa na kichanganuzi cha virusi na hakikutumwa.',
        'unavailable' => 'Kukagua viambatisho hakupatikani kwa sasa. Tafadhali jaribu tena baadaye.',
    ],
    'attachment' => [
        'invalid_mime' => 'Aina ya kiambatisho hairuhusiwi.',
        'too_large' => 'Kiambatisho kinapita kikomo cha MB 5.',
    ],
    'message' => [
        'spam_rejected' => 'Ujumbe umekataliwa kama spam. Tafadhali kagua na utume tena.',
        'deleted_by_sender' => 'Ujumbe umefutwa na mtumaji.',
        'thread_locked_by_landlord' => 'Mfululizo umefungwa na mmiliki.',
        'thread_unlocked_by_landlord' => 'Mfululizo umefunguliwa na mmiliki.',
    ],
    'notification' => [
        'subject' => 'Ujumbe mpya kutoka :sender',
        'sender_unknown' => 'Timu ya mali',
    ],

    'chat' => [
        'today' => 'Leo',
        'yesterday' => 'Jana',
        'unread' => 'Ujumbe ambazo hazijasomwa',
        'sent' => 'Imetumwa',
        'placeholder' => 'Andika ujumbe…',
        'send' => 'Tuma',
        'attach' => 'Ambatisha faili',
        'body_label' => 'Mwili wa ujumbe',
        'locked' => 'Mfululizo huu ni {status} na hauwezi kupokea ujumbe mpya.',
        'chars_remaining' => 'Herufi {count} imebaki | Herufi {count} zimebaki',
        'jump_latest' => 'Ruka kwenye ujumbe wa hivi karibuni',
        'sending' => 'Inatuma…',
        'retry' => 'Bofya kujaribu tena',
        'reply' => 'Jibu',
        'replying_to' => 'Unajibu {name}',
        'cancel_reply' => 'Ghairi jibu',
        'reactions' => [
            'add' => 'Ongeza mwitikio',
            'react_with' => 'Itikie kwa {emoji}',
            'pill_label' => '{emoji}, mwitikio {count}',
        ],
        'attachment' => [
            'unavailable' => 'Kiambatisho hakipatikani',
            'open_image' => 'Fungua picha',
            'close' => 'Funga',
        ],
    ],

    'show' => [
        'head_title' => 'Ujumbe kutoka kwa {name}',
        'back' => 'Rudi Kikasha',
        'replying_to' => 'Unajibu: {subject}',
        'sent_at' => 'Ilitumwa {date}',
        'auto_created_ticket' => 'Tikiti iliyoundwa kiotomatiki:',
        'mark_as_read' => 'Weka Kuwa Imesomwa',
        'attachments' => 'Viambatisho ({count})',
        'attachment_alt' => 'Kiambatisho {number}',
        'reply_via' => 'Jibu kupitia {channel}',
        'reply_placeholder' => 'Andika jibu lako...',
        'chars_remaining' => 'Herufi {count} zimebaki',
        'sending' => 'Inatuma...',
        'send_reply' => 'Tuma Jibu',
    ],

    'title' => 'Kikasha',
    'subtitle' => 'Ujumbe wa wapangaji kutoka WhatsApp na SMS',
    'unread_count' => '({count} ambazo hazijasomwa)',
    'mark_all_read' => 'Weka Zote Kuwa Zimesomwa',
    'confirm_mark_all_read' => 'Weka ujumbe wote kuwa umesomwa?',
    'search_placeholder' => 'Tafuta kwa jina la mpangaji, simu, au ujumbe...',
    'filter' => [
        'all' => 'Ujumbe Wote',
        'unread' => 'Hazijasomwa',
        'processed' => 'Zimesomwa / Zimeshughulikiwa',
    ],
    'table' => [
        'tenant' => 'Mpangaji',
        'message' => 'Ujumbe',
        'source' => 'Chanzo',
        'status' => 'Hali',
        'time' => 'Wakati',
        'actions' => 'Vitendo',
    ],
    'status' => [
        'received' => 'Hazijasomwa',
        'processed' => 'Zimesomwa',
        'action_taken' => 'Zimeshughulikiwa',
        'ignored' => 'Zimepuuzwa',
    ],
    'reply_prefix' => 'Jibu: {subject}',
    'ticket_label' => 'Tikiti #{id}',
    'mark_read_title' => 'Weka kuwa imesomwa',
    'mark_read' => 'Weka Imesomwa',
    'view' => 'Tazama',
    'empty' => [
        'title' => 'Hakuna ujumbe',
        'description' => 'Wapangaji wanapojibu arifa kupitia WhatsApp au SMS, ujumbe wao utaonekana hapa.',
    ],
    'pagination' => [
        'previous' => 'Iliyopita',
        'next' => 'Inayofuata',
        'showing' => 'Inaonyesha {from} hadi {to} kati ya {total} ujumbe',
    ],
];
