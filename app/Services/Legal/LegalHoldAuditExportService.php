<?php

declare(strict_types=1);

namespace App\Services\Legal;

use App\Models\AuditLog;
use App\Models\LegalHold;
use App\Models\LegalMatter;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Phase-65 AUDIT-DEPTH-1: regulator-ready CSV export of every
 * legal-hold action over a date window. Pulls from audit_logs
 * filtered by auditable_type=LegalHold::class for the landlord.
 *
 * UTF-8 BOM + explicit \n line endings + columns: event_at,
 * event_type, subject_type, subject_id, reason, actor_user_id,
 * actor_user_name, lawful_basis. Excel opens with correct encoding
 * without the import-wizard detour.
 */
class LegalHoldAuditExportService
{
    public const MAX_RANGE_DAYS = 730;

    public function exportToCsv(User $landlord, Carbon $from, Carbon $to): string
    {
        $rows = AuditLog::query()
            ->forModel(LegalHold::class)
            ->where('landlord_id', $landlord->id)
            ->whereBetween('created_at', [$from->startOfDay(), $to->endOfDay()])
            ->with('user:id,name')
            ->orderBy('created_at')
            ->get();

        // Nest under exports/{landlord_id}/legal-hold-audit/ so the
        // Phase 59 export_zip retention sweep (which walks
        // exports/<userDir>/<exportDir>/) finds these CSVs and
        // honours the 7-day window. A flat exports/foo.csv would
        // leak forever.
        $relativePath = 'exports/'.$landlord->id.'/legal-hold-audit/'.Str::random(16).'.csv';

        $buffer = "\xEF\xBB\xBF";
        $buffer .= "event_at,event_type,subject_type,subject_id,reason,actor_user_id,actor_user_name,lawful_basis\n";

        foreach ($rows as $row) {
            $metadata = is_array($row->metadata) ? $row->metadata : [];
            $newValues = is_array($row->new_values) ? $row->new_values : [];
            $oldValues = is_array($row->old_values) ? $row->old_values : [];

            $subjectType = $newValues['holdable_type'] ?? $oldValues['holdable_type'] ?? '';
            $subjectId = $newValues['holdable_id'] ?? $oldValues['holdable_id'] ?? '';
            $reason = $newValues['reason'] ?? $oldValues['reason'] ?? '';
            $lawfulBasis = $metadata['lawful_basis'] ?? 'legal_obligation';

            $buffer .= $this->csvLine([
                $row->created_at?->toIso8601String() ?? '',
                (string) $row->event_type,
                $this->shortType((string) $subjectType),
                (string) $subjectId,
                (string) $reason,
                (string) ($row->user_id ?? ''),
                (string) ($row->user?->name ?? ''),
                (string) $lawfulBasis,
            ]);
        }

        Storage::tenant((int) $landlord->id)->put($relativePath, $buffer);

        return $relativePath;
    }

    /**
     * Phase-68 HISTORY-2: per-subject chain-of-custody CSV. Sourced from
     * the legal_holds rows themselves (each row = one held->released
     * lifecycle), not audit_logs. Stored under exports/{actor}/
     * legal-hold-history/ so the Phase-59 export retention sweep honours
     * the 7-day window. Authorization is the caller's responsibility.
     */
    public function exportSubjectHistoryToCsv(User $actor, string $subjectClass, int $subjectId): string
    {
        $rows = LegalHold::query()
            ->forSubject($subjectClass, $subjectId)
            ->with(['heldBy:id,name', 'releasedBy:id,name'])
            ->orderBy('held_at')
            ->get();

        $relativePath = 'exports/'.$actor->id.'/legal-hold-history/'.Str::random(16).'.csv';

        $buffer = "\xEF\xBB\xBF";
        $buffer .= "held_at,reason,held_by_id,held_by_name,released_at,released_by_id,released_by_name,status,lawful_basis\n";

        foreach ($rows as $row) {
            $buffer .= $this->csvLine([
                $row->held_at?->toIso8601String() ?? '',
                (string) $row->reason,
                (string) ($row->held_by ?? ''),
                (string) ($row->heldBy?->name ?? ''),
                $row->released_at?->toIso8601String() ?? '',
                (string) ($row->released_by ?? ''),
                (string) ($row->releasedBy?->name ?? ''),
                $row->isActive() ? 'active' : 'released',
                $row->getLawfulBasis(),
            ]);
        }

        Storage::tenant((int) $actor->id)->put($relativePath, $buffer);

        return $relativePath;
    }

    /**
     * Phase-72 MATTER-GROUPING: matter-scoped chain-of-custody CSV — every hold
     * linked to one matter. Same UTF-8 BOM + injection guard + retention path
     * as the per-subject export. Authorization is the caller's responsibility.
     */
    public function exportMatterToCsv(User $actor, LegalMatter $matter): string
    {
        $rows = LegalHold::query()
            ->where('legal_matter_id', $matter->id)
            ->with(['heldBy:id,name', 'releasedBy:id,name'])
            ->orderBy('held_at')
            ->get();

        $relativePath = 'exports/'.$actor->id.'/legal-hold-matter/'.Str::random(16).'.csv';

        $buffer = "\xEF\xBB\xBF";
        $buffer .= "subject_type,subject_id,held_at,reason,held_by_name,released_at,released_by_name,status,lawful_basis\n";

        foreach ($rows as $row) {
            $buffer .= $this->csvLine([
                $this->shortType((string) $row->holdable_type),
                (string) $row->holdable_id,
                $row->held_at?->toIso8601String() ?? '',
                (string) $row->reason,
                (string) ($row->heldBy?->name ?? ''),
                $row->released_at?->toIso8601String() ?? '',
                (string) ($row->releasedBy?->name ?? ''),
                $row->isActive() ? 'active' : 'released',
                $row->getLawfulBasis(),
            ]);
        }

        Storage::tenant((int) $actor->id)->put($relativePath, $buffer);

        return $relativePath;
    }

    /**
     * @param  array<int, string>  $fields
     */
    private function csvLine(array $fields): string
    {
        return implode(',', array_map(fn (string $f) => $this->csvEscape($f), $fields))."\n";
    }

    private function csvEscape(string $value): string
    {
        if ($value === '') {
            return '';
        }

        $value = $this->guardCsvInjection($value);

        if (! $this->requiresCsvQuoting($value)) {
            return $value;
        }

        return '"'.str_replace('"', '""', $value).'"';
    }

    /**
     * CSV-injection guard: a reason like `=HYPERLINK(...)` is executed
     * as a formula when Excel/Sheets opens the file. Prefix with a
     * leading apostrophe so the cell renders as text. Apply BEFORE
     * quoting so the escaped string is itself quoted when needed.
     */
    private function guardCsvInjection(string $value): string
    {
        $first = $value[0];
        if (in_array($first, ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'".$value;
        }

        return $value;
    }

    private function requiresCsvQuoting(string $value): bool
    {
        return str_contains($value, ',')
            || str_contains($value, '"')
            || str_contains($value, "\n")
            || str_contains($value, "\r");
    }

    private function shortType(string $fqcn): string
    {
        if ($fqcn === '') {
            return '';
        }

        $parts = explode('\\', $fqcn);

        return end($parts);
    }
}
