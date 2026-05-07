<?php

namespace App\Models;

use App\Enums\TicketStatus;
use App\Traits\TenantScope;
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
    use TenantScope;

    protected $fillable = [
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
    ];

    protected $casts = [
        'status' => TicketStatus::class,
        'resolved_at' => 'datetime',
        'closed_at' => 'datetime',
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
