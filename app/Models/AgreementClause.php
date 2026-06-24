<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    public function agreement(): BelongsTo
    {
        return $this->belongsTo(ManagementAgreement::class, 'management_agreement_id');
    }

    public function clause(): BelongsTo
    {
        return $this->belongsTo(Clause::class);
    }

    public function render(): string
    {
        return Clause::renderTemplate($this->clause?->body_template ?? '', $this->params ?? []);
    }
}
