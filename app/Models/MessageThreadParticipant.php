<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Phase-63 INBOX-THREAD-3: pivot between message_threads and users.
 *
 * The presence of a row here is the authoritative "this user can see
 * this thread" check. last_read_at drives read-receipt UX (see
 * Phase 63 INBOX-REALTIME-2).
 */
class MessageThreadParticipant extends Pivot
{
    protected $table = 'message_thread_participants';

    public $incrementing = true;

    public const ROLE_LANDLORD = 'landlord';

    public const ROLE_CARETAKER = 'caretaker';

    public const ROLE_TENANT = 'tenant';

    public const ROLES = [
        self::ROLE_LANDLORD,
        self::ROLE_CARETAKER,
        self::ROLE_TENANT,
    ];

    protected $fillable = [
        'thread_id',
        'user_id',
        'role',
        'last_read_at',
    ];

    protected $casts = [
        'last_read_at' => 'datetime',
    ];
}
