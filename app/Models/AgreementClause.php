<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ClauseBinding;
use App\Exceptions\DataIntegrityException;
use App\Services\ManagementFee\FeeClauseParams;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

/**
 * Slice-2: a clause instance inside an agreement — the chosen clause plus its
 * filled params. Scoped via its parent agreement (no own landlord_id). render()
 * produces the clause's final legal text for the agreement snapshot.
 */
class AgreementClause extends Model
{
    /** @use HasFactory<\Database\Factories\AgreementClauseFactory> */
    use HasFactory;

    protected $fillable = [
        'management_agreement_id',
        'clause_id',
        'params',
        'position',
    ];

    protected function casts(): array
    {
        return [
            'params' => 'array',
        ];
    }

    protected static function booted(): void
    {
        // Enforce clause exclusivity: an agreement may hold at most one instance
        // of an exclusive binding (e.g. the fee clause feeClause() relies on, so
        // PR 2.3 can never lock config from an ambiguous second instance).
        static::creating(function (AgreementClause $instance): void {
            $clause = $instance->clause ?? Clause::find($instance->clause_id);
            if ($clause === null || ! $clause->is_exclusive) {
                return;
            }

            $duplicate = static::query()
                ->where('management_agreement_id', $instance->management_agreement_id)
                ->whereHas('clause', fn ($query) => $query->where('binding', $clause->binding))
                ->exists();

            if ($duplicate) {
                throw new DataIntegrityException(
                    "An agreement may hold only one {$clause->binding->value} clause.",
                    'agreement.clause.duplicate_binding',
                    ['binding' => $clause->binding->value],
                );
            }
        });
    }

    public function agreement(): BelongsTo
    {
        return $this->belongsTo(ManagementAgreement::class, 'management_agreement_id');
    }

    public function clause(): BelongsTo
    {
        return $this->belongsTo(Clause::class);
    }

    /**
     * The typed, validated fee params — the only way the applicator (PR 2.3)
     * should read fee values. Null for non-fee clauses.
     */
    public function feeParams(): ?FeeClauseParams
    {
        $this->loadMissing('clause');

        if ($this->clause?->binding !== ClauseBinding::ManagementFee) {
            return null;
        }

        return FeeClauseParams::fromParams($this->params ?? []);
    }

    public function render(): string
    {
        $this->loadMissing('clause');

        if ($this->clause === null) {
            throw new RuntimeException("AgreementClause {$this->id} has no clause to render.");
        }

        $params = $this->params ?? [];

        // The fee clause's body reads {fee_description}; derive it from the
        // governed params so the structured values stay the single source of
        // truth (and a malformed fee can't be silently rendered).
        if ($this->clause->binding === ClauseBinding::ManagementFee && ! array_key_exists('fee_description', $params)) {
            $params['fee_description'] = FeeClauseParams::fromParams($params)->describe();
        }

        return Clause::renderTemplate($this->clause->body_template, $params);
    }
}
