<?php

namespace App\Models;

use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Model;

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
        'resolved_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    // --- RELATIONSHIPS ---

    public function building()
    {
        return $this->belongsTo(Building::class);
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function reporter()
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function activities()
    {
        return $this->hasMany(TicketActivity::class)->orderBy('created_at');
    }

    public function comments()
    {
        return $this->hasMany(TicketComment::class)->orderBy('created_at');
    }

    public function feedback()
    {
        return $this->hasOne(TicketFeedback::class);
    }

    public function attachments()
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    // --- SCOPES ---

    public function scopeIssues($query)
    {
        return $query->where('category', 'issue');
    }

    public function scopeComplaints($query)
    {
        return $query->where('category', 'complaint');
    }

    public function scopeOpen($query)
    {
        return $query->whereIn('status', ['open', 'acknowledged', 'in_progress']);
    }

    public function scopeResolved($query)
    {
        return $query->where('status', 'resolved');
    }

    public function scopeClosed($query)
    {
        return $query->where('status', 'closed');
    }

    public function scopeForBuilding($query, $buildingId)
    {
        return $query->where('building_id', $buildingId);
    }

    public function scopeAssignedTo($query, $userId)
    {
        return $query->where('assigned_to', $userId);
    }

    public function scopeUnassigned($query)
    {
        return $query->whereNull('assigned_to');
    }

    public function scopeByPriority($query, $priority)
    {
        return $query->where('priority', $priority);
    }

    public function scopeUrgent($query)
    {
        return $query->where('priority', 'urgent');
    }

    // --- HELPERS ---

    public function isOpen(): bool
    {
        return in_array($this->status, ['open', 'acknowledged', 'in_progress']);
    }

    public function isResolved(): bool
    {
        return $this->status === 'resolved';
    }

    public function isClosed(): bool
    {
        return $this->status === 'closed';
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
        return in_array($this->status, ['open', 'acknowledged']);
    }

    public function hasFeedback(): bool
    {
        return $this->feedback()->exists();
    }

    // --- STATUS TRANSITIONS ---

    public function acknowledge(): void
    {
        $this->update(['status' => 'acknowledged']);
    }

    public function startWork(): void
    {
        $this->update(['status' => 'in_progress']);
    }

    public function resolve(?string $notes = null): void
    {
        $this->update([
            'status' => 'resolved',
            'resolution_notes' => $notes,
            'resolved_at' => now(),
        ]);
    }

    public function close(): void
    {
        $this->update([
            'status' => 'closed',
            'closed_at' => now(),
        ]);
    }

    public function cancel(): void
    {
        $this->update(['status' => 'cancelled']);
    }

    // --- ACTIVITY LOGGING ---

    public function logActivity(string $action, ?string $oldValue = null, ?string $newValue = null, ?string $description = null, ?int $userId = null): TicketActivity
    {
        return $this->activities()->create([
            'user_id' => $userId ?? auth()->id(),
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
        return [
            'open' => 'Open',
            'acknowledged' => 'Acknowledged',
            'in_progress' => 'In Progress',
            'resolved' => 'Resolved',
            'closed' => 'Closed',
            'cancelled' => 'Cancelled',
        ];
    }
}
