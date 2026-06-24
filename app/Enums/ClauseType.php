<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Slice-2: the two agreement families. Management (owner↔manager) is built in
 * PR 2.x; Tenancy (lessor↔tenant) arrives in Slice 6 — enumerated now so the
 * clause table is shared.
 */
enum ClauseType: string
{
    case Management = 'management';
    case Tenancy = 'tenancy';
}
