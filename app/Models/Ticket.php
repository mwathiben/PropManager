<?php

namespace App\Models;

use App\Enums\TicketStatus;
use App\Models\Concerns\HasLegalHolds;
use App\Models\Concerns\RowVersion;
use App\Traits\Auditable;
use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Auth;

/**
 * @property int $id
 * @property int $landlord_id
 * @property int $building_id
 * @property int|null $unit_id
 * @property int|null $reporter_id
 * @property int|null $assigned_to
 * @property string $category
 * @property string $subcategory
 * @property string $title
 * @property string $description
 * @property string $priority
 * @property string $status
 * @property string|null $location
 * @property string|null $resolution_notes
 * @property \Carbon\Carbon|null $resolved_at
 * @property \Carbon\Carbon|null $closed_at
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read Building $building
 * @property-read Unit|null $unit
 * @property-read User|null $reporter
 * @property-read User|null $assignee
 * @property-read \Illuminate\Database\Eloquent\Collection<TicketActivity> $activities
 * @property-read \Illuminate\Database\Eloquent\Collection<TicketComment> $comments
 * @property-read TicketFeedback|null $feedback
 * @property-read \Illuminate\Database\Eloquent\Collection<Document> $attachments
 */
class Ticket extends Model
{
    use Auditable;
    use HasFactory;
    use HasLegalHolds;
    use RowVersion;
    use TenantScope;

    protected $fillable = [
        'version',
        'landlord_id',
        'building_id',
        'unit_id',
        'reporter_id',
        'assigned_to',
        'category',
        'subcategory',
        'title',
        'description',
        'priority',
        'status',
        'location',
        'resolution_notes',
        'resolved_at',
        'closed_at',
        'sla_due_at',
        'first_response_at',
        'resolution_due_at',
        'vendor_id',
        'vendor_status',
        'vendor_responded_at',
    ];

    protected $casts = [
        'status' => TicketStatus::class,
        'resolved_at' => 'datetime',
        'closed_at' => 'datetime',
        'sla_due_at' => 'datetime',
        'first_response_at' => 'datetime',
        'resolution_due_at' => 'datetime',
        'vendor_responded_at' => 'datetime',
    ];

    /**
     * Phase-28 TENANT-MAINT-1: SLA window per priority, in seconds.
     * urgent=4h, high=24h, medium=72h, low=168h. boot()->creating sets
     * sla_due_at = created_at + matching seconds.
     */
    public const SLA_SECONDS = [
        'urgent' => 14400,
        'high' => 86400,
        'medium' => 259200,
        'low' => 604800,
    ];

    /**
     * Phase-49 TICKETS-SLA-DEEP-1: resolution SLA window per priority.
     * urgent=24h, high=7d, medium=14d, low=30d — longer than response
     * windows because resolution often needs parts/vendors. booted()
     * creating sets resolution_due_at; tickets:audit-sla detects breach
     * separately from first-response breach.
     */
    public const RESOLUTION_SLA_SECONDS = [
        'urgent' => 86400,
        'high' => 604800,
        'medium' => 1209600,
        'low' => 2592000,
    ];

    public function building(): BelongsTo
    {
        return $this->belongsTo(Building::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * Phase-49 VENDOR-MARKETPLACE-1: external contractor doing the work.
     * Not mutually exclusive with assignee — caretaker can oversee while
     * vendor executes.
     */
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Vendor::class);
    }

    /**
     * Phase-49 PARTS-INVENTORY-2: parts consumed resolving this ticket,
     * captured via the ticket_parts pivot at the moment of recording.
     */
    public function parts(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(\App\Models\Part::class, 'ticket_parts')
            ->withPivot(['qty_used', 'cost_allocated_cents', 'recorded_by', 'recorded_at']);
    }

    /**
     * Phase-49 MAINTENANCE-COSTS-1: per-ticket cost rows (parts auto-seeded
     * by TicketResolutionService; vendor/labor/other captured via the
     * TicketCostService write path).
     */
    public function costs(): HasMany
    {
        return $this->hasMany(\App\Models\TicketCost::class);
    }

    /**
     * Phase-49 MAINTENANCE-COSTS-2: total maintenance cost in cents
     * across all categories. Reads from ticket_costs (parts-category
     * row is kept in sync with ticket_parts pivot by TicketResolutionService).
     */
    public function totalMaintenanceCost(): int
    {
        return (int) $this->costs()->sum('amount_cents');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(TicketActivity::class)->orderBy('created_at');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(TicketComment::class)->orderBy('created_at');
    }

    public function feedback(): HasOne
    {
        return $this->hasOne(TicketFeedback::class);
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    /**
     * Phase-28 TENANT-MAINT-1: set sla_due_at on create from the
     * priority's SLA window. The boot() block must call parent::boot()
     * because TenantScope's boot also runs creating hooks.
     */
    protected static function booted(): void
    {
        // Phase-49 SLA-PER-CATEGORY-2: SLA stamping moved to
        // TicketObserver::creating so it can use SlaDefinitionService
        // (which needs landlord_id, set by the observer first). The
        // service has SLA_SECONDS / RESOLUTION_SLA_SECONDS as fallback,
        // so the constants on this model remain the source of truth
        // when no override row matches.
    }

    /**
     * @param  Builder<Ticket>  $query
     */
    public function scopeBreachedSla(Builder $query): Builder
    {
        return $query->whereNotNull('sla_due_at')
            ->where('sla_due_at', '<', now())
            ->whereNull('first_response_at');
    }

    /**
     * Phase-49 TICKETS-SLA-DEEP-2: resolution-stage breach. A ticket
     * that's past resolution_due_at AND not yet resolved.
     *
     * @param  Builder<Ticket>  $query
     */
    public function scopeBreachedResolutionSla(Builder $query): Builder
    {
        return $query->whereNotNull('resolution_due_at')
            ->where('resolution_due_at', '<', now())
            ->whereNull('resolved_at')
            ->whereNotIn('status', ['resolved', 'closed', 'cancelled']);
    }

    /**
     * @param  Builder<Ticket>  $query
     */
    public function scopeIssues(Builder $query): Builder
    {
        return $query->where('category', 'issue');
    }

    /**
     * @param  Builder<Ticket>  $query
     */
    public function scopeComplaints(Builder $query): Builder
    {
        return $query->where('category', 'complaint');
    }

    /**
     * @param  Builder<Ticket>  $query
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', [TicketStatus::Open, TicketStatus::Acknowledged, TicketStatus::InProgress]);
    }

    /**
     * @param  Builder<Ticket>  $query
     */
    public function scopeResolved(Builder $query): Builder
    {
        return $query->where('status', TicketStatus::Resolved);
    }

    /**
     * @param  Builder<Ticket>  $query
     */
    public function scopeClosed(Builder $query): Builder
    {
        return $query->where('status', TicketStatus::Closed);
    }

    /**
     * @param  Builder<Ticket>  $query
     */
    public function scopeForBuilding(Builder $query, int $buildingId): Builder
    {
        return $query->where('building_id', $buildingId);
    }

    /**
     * @param  Builder<Ticket>  $query
     */
    public function scopeAssignedTo(Builder $query, int $userId): Builder
    {
        return $query->where('assigned_to', $userId);
    }

    /**
     * @param  Builder<Ticket>  $query
     */
    public function scopeUnassigned(Builder $query): Builder
    {
        return $query->whereNull('assigned_to');
    }

    /**
     * @param  Builder<Ticket>  $query
     */
    public function scopeByPriority(Builder $query, string $priority): Builder
    {
        return $query->where('priority', $priority);
    }

    /**
     * @param  Builder<Ticket>  $query
     */
    public function scopeUrgent(Builder $query): Builder
    {
        return $query->where('priority', 'urgent');
    }

    public function isOpen(): bool
    {
        return in_array($this->status, [TicketStatus::Open, TicketStatus::Acknowledged, TicketStatus::InProgress]);
    }

    public function isResolved(): bool
    {
        return $this->status === TicketStatus::Resolved;
    }

    public function isClosed(): bool
    {
        return $this->status === TicketStatus::Closed;
    }

    public function isIssue(): bool
    {
        return $this->category === 'issue';
    }

    public function isComplaint(): bool
    {
        return $this->category === 'complaint';
    }

    public function canBeEdited(): bool
    {
        return in_array($this->status, [TicketStatus::Open, TicketStatus::Acknowledged]);
    }

    public function hasFeedback(): bool
    {
        return $this->feedback()->exists();
    }

    // --- STATUS TRANSITIONS ---

    public function acknowledge(): void
    {
        $this->update(['status' => TicketStatus::Acknowledged]);
    }

    public function startWork(): void
    {
        $this->update(['status' => TicketStatus::InProgress]);
    }

    public function resolve(?string $notes = null): void
    {
        $this->update([
            'status' => TicketStatus::Resolved,
            'resolution_notes' => $notes,
            'resolved_at' => now(),
        ]);
    }

    public function close(): void
    {
        $this->update([
            'status' => TicketStatus::Closed,
            'closed_at' => now(),
        ]);
    }

    public function cancel(): void
    {
        $this->update(['status' => TicketStatus::Cancelled]);
    }

    // --- ACTIVITY LOGGING ---

    public function logActivity(string $action, ?string $oldValue = null, ?string $newValue = null, ?string $description = null, ?int $userId = null): TicketActivity
    {
        return $this->activities()->create([
            'user_id' => $userId ?? Auth::id(),
            'action' => $action,
            'old_value' => $oldValue,
            'new_value' => $newValue,
            'description' => $description,
            'created_at' => now(),
        ]);
    }

    // --- SUBCATEGORY OPTIONS ---

    public static function issueSubcategories(): array
    {
        return [
            'plumbing' => 'Plumbing',
            'electrical' => 'Electrical',
            'water_supply' => 'Water Supply',
            'structural' => 'Structural',
            'appliances' => 'Appliances',
            'painting' => 'Painting/Finishing',
            'pest_control' => 'Pest Control',
            'other' => 'Other',
        ];
    }

    public static function complaintSubcategories(): array
    {
        return [
            'noise' => 'Noise',
            'cleanliness' => 'Cleanliness',
            'garbage' => 'Garbage/Waste',
            'parking' => 'Parking',
            'security' => 'Security',
            'neighbor_behavior' => 'Neighbor Behavior',
            'service_delivery' => 'Service Delivery',
            'other' => 'Other',
        ];
    }

    public static function allSubcategories(): array
    {
        return [
            'issue' => self::issueSubcategories(),
            'complaint' => self::complaintSubcategories(),
        ];
    }

    public static function priorities(): array
    {
        return [
            'low' => 'Low',
            'medium' => 'Medium',
            'high' => 'High',
            'urgent' => 'Urgent',
        ];
    }

    public static function statuses(): array
    {
        return TicketStatus::labelsMap();
    }
}
