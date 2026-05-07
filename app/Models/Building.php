<?php

namespace App\Models;

use App\Enums\Currency;
use App\Traits\Auditable;
use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $property_id
 * @property int|null $parent_building_id
 * @property int $landlord_id
 * @property int|null $caretaker_id
 * @property string $name
 * @property bool $is_wing
 * @property string|null $unit_prefix
 * @property string|null $building_type
 * @property string|null $address
 * @property string|null $description
 * @property int|null $total_floors
 * @property int|null $units_per_floor
 * @property string|null $water_billing_type
 * @property float|null $water_flat_rate
 * @property float|null $water_unit_rate
 * @property array|null $coordinates
 * @property array|null $amenities
 * @property array|null $photos
 * @property bool $auto_generate_invoices
 * @property int|null $invoice_generation_day
 * @property bool $auto_send_invoices
 * @property Currency|null $currency
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read Property $property
 * @property-read User|null $caretaker
 * @property-read Building|null $parentBuilding
 * @property-read \Illuminate\Database\Eloquent\Collection<Unit> $units
 * @property-read \Illuminate\Database\Eloquent\Collection<Building> $wings
 * @property-read \Illuminate\Database\Eloquent\Collection<Ticket> $tickets
 * @property-read \Illuminate\Database\Eloquent\Collection<KycRequirement> $kycRequirements
 */
class Building extends Model
{
    use Auditable, HasFactory, SoftDeletes, TenantScope;

    protected $fillable = [
        'property_id',
        'parent_building_id',
        'landlord_id',
        'caretaker_id',
        'name',
        'is_wing',
        'unit_prefix',
        'building_type',
        'address',
        'description',
        'total_floors',
        'units_per_floor',
        'water_billing_type',
        'water_flat_rate',
        'water_unit_rate',
        'coordinates',
        'amenities',
        'photos',
        'auto_generate_invoices',
        'invoice_generation_day',
        'auto_send_invoices',
        'currency',
    ];

    protected $casts = [
        'total_floors' => 'integer',
        'units_per_floor' => 'integer',
        'water_flat_rate' => 'decimal:2',
        'water_unit_rate' => 'decimal:2',
        'coordinates' => 'array',
        'amenities' => 'array',
        'photos' => 'array',
        'is_wing' => 'boolean',
        'auto_generate_invoices' => 'boolean',
        'auto_send_invoices' => 'boolean',
        'invoice_generation_day' => 'integer',
        'currency' => Currency::class,
    ];

    /**
     * Available building types with display labels.
     */
    public const BUILDING_TYPES = [
        'residential_apartment' => 'Residential Apartment',
        'office_block' => 'Office Block',
        'warehouse' => 'Warehouse',
        'go_down' => 'Go-Down',
        'maisonette' => 'Maisonette',
        'bungalow' => 'Bungalow',
        'single_unit_rental' => 'Single Unit Rental',
        'mixed_use' => 'Mixed Use',
        'commercial_plaza' => 'Commercial Plaza',
        'townhouse' => 'Townhouse',
        'bedsitter_block' => 'Bedsitter Block',
        'hostel' => 'Hostel/Student Housing',
    ];

    /**
     * Predefined amenities with categories.
     */
    public const AMENITY_OPTIONS = [
        'utilities' => [
            'wifi' => 'WiFi/Internet',
            'hot_water' => 'Hot Water/Shower',
            'generator' => 'Backup Generator',
            'solar' => 'Solar Power',
            'borehole' => 'Borehole Water',
            'water_tank' => 'Water Tank/Storage',
            'fiber_ready' => 'Fiber Internet Ready',
        ],
        'security' => [
            'cctv' => 'CCTV Cameras',
            'security_guard' => '24/7 Security Guard',
            'intercom' => 'Intercom System',
            'electric_fence' => 'Electric Fence',
            'gated' => 'Gated Compound',
            'biometric_access' => 'Biometric Access',
            'security_alarm' => 'Security Alarm System',
        ],
        'parking' => [
            'parking' => 'Parking Space',
            'covered_parking' => 'Covered Parking',
            'motorcycle_parking' => 'Motorcycle Parking',
            'visitor_parking' => 'Visitor Parking',
            'parking_per_unit' => 'Dedicated Parking per Unit',
        ],
        'common_amenities' => [
            'elevator' => 'Elevator/Lift',
            'gym' => 'Gym/Fitness Center',
            'swimming_pool' => 'Swimming Pool',
            'playground' => 'Children\'s Playground',
            'laundry' => 'Laundry Room',
            'rooftop' => 'Rooftop Terrace',
            'bbq_area' => 'BBQ Area',
            'clubhouse' => 'Clubhouse',
            'meeting_room' => 'Meeting/Conference Room',
        ],
        'unit_features' => [
            'balcony' => 'Balcony',
            'garden' => 'Garden/Lawn',
            'pets_allowed' => 'Pets Allowed',
            'furnished' => 'Furnished Units Available',
            'air_conditioning' => 'Air Conditioning',
            'washer_hookup' => 'Washer/Dryer Hookup',
            'built_in_wardrobes' => 'Built-in Wardrobes',
            'en_suite' => 'En-suite Bathroom',
        ],
        'neighborhood' => [
            'near_schools' => 'Near Schools',
            'near_hospital' => 'Near Hospital',
            'near_shopping' => 'Near Shopping Center',
            'public_transport' => 'Public Transport Access',
            'quiet_area' => 'Quiet Neighborhood',
            'main_road_access' => 'Main Road Access',
        ],
    ];

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function units(): HasMany
    {
        return $this->hasMany(Unit::class)->orderBy('floor_number')->orderBy('unit_number');
    }

    public function caretaker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'caretaker_id');
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    public function parentBuilding(): BelongsTo
    {
        return $this->belongsTo(Building::class, 'parent_building_id');
    }

    public function wings(): HasMany
    {
        return $this->hasMany(Building::class, 'parent_building_id');
    }

    public function kycRequirements(): HasMany
    {
        return $this->hasMany(KycRequirement::class);
    }

    public function moveOutDeductionCategories(): HasMany
    {
        return $this->hasMany(MoveOutDeductionCategory::class);
    }

    // --- WING HELPERS ---

    /**
     * Exclude parent container buildings that have wings.
     * Keeps: wings + standalone buildings. Excludes: empty parent shells.
     */
    public function scopeExcludeParentContainers($query)
    {
        return $query->where(function ($q) {
            $q->where('is_wing', true)
                ->orWhereDoesntHave('wings');
        });
    }

    /**
     * Check if this building is a wing (has a parent building).
     */
    public function isWing(): bool
    {
        return $this->parent_building_id !== null;
    }

    /**
     * Check if this building has wings (child buildings).
     */
    public function hasWings(): bool
    {
        return $this->wings()->exists();
    }

    /**
     * Get the main building (self if not a wing, parent if a wing).
     */
    public function getMainBuilding(): Building
    {
        return $this->isWing() ? $this->parentBuilding : $this;
    }

    /**
     * Get all units including from wings.
     * For a building with wings: returns units from all wings.
     * For a standalone building: returns its own units.
     */
    public function allUnits()
    {
        if ($this->hasWings()) {
            $wingIds = $this->wings()->pluck('id')->toArray();

            return Unit::whereIn('building_id', array_merge([$this->id], $wingIds));
        }

        return $this->units();
    }

    /**
     * Get all floors across this building and its wings.
     */
    public function getAllFloors(): array
    {
        return $this->allUnits()
            ->distinct()
            ->orderBy('floor_number', 'desc')
            ->pluck('floor_number')
            ->toArray();
    }

    // --- CURRENCY HELPERS ---

    public function getEffectiveCurrency(): Currency
    {
        if ($this->currency !== null) {
            return $this->currency;
        }

        $config = PaymentConfiguration::where('landlord_id', $this->landlord_id)->first();

        return $config?->default_currency ?? Currency::default();
    }

    // --- WATER BILLING HELPERS ---

    /**
     * Check if water billing is enabled for this building.
     */
    public function hasWaterEnabled(): bool
    {
        return $this->water_billing_type !== null;
    }

    /**
     * Check if using consumption-based (meter reading) billing.
     */
    public function usesConsumptionBilling(): bool
    {
        return $this->water_billing_type === 'consumption';
    }

    /**
     * Check if using flat rate billing.
     */
    public function usesFlatRateBilling(): bool
    {
        return $this->water_billing_type === 'flat_rate';
    }

    /**
     * Get the water charge for a unit.
     * For flat rate, returns the building's flat rate.
     * For consumption, returns 0 (calculated from readings separately).
     */
    public function getWaterChargeForUnit(): float
    {
        if ($this->usesFlatRateBilling()) {
            return (float) ($this->water_flat_rate ?? 0);
        }

        // Consumption billing is calculated from readings
        return 0;
    }

    /**
     * Get available water billing types.
     */
    public static function waterBillingTypes(): array
    {
        return [
            'consumption' => 'Consumption-based (Meter Readings)',
            'flat_rate' => 'Flat Monthly Rate',
        ];
    }

    // --- BUILDING TYPE & AMENITY HELPERS ---

    /**
     * Get building type display label.
     */
    public function getBuildingTypeLabel(): string
    {
        return self::BUILDING_TYPES[$this->building_type] ?? ucwords(str_replace('_', ' ', $this->building_type));
    }

    /**
     * Get flat list of all predefined amenity keys.
     */
    public static function getAllAmenityKeys(): array
    {
        $keys = [];
        foreach (self::AMENITY_OPTIONS as $category => $amenities) {
            $keys = array_merge($keys, array_keys($amenities));
        }

        return $keys;
    }

    /**
     * Check if building has a specific amenity.
     */
    public function hasAmenity(string $key): bool
    {
        $amenities = $this->amenities ?? [];

        return in_array($key, $amenities['selected'] ?? []);
    }

    /**
     * Get all active amenities with their labels.
     */
    public function getActiveAmenities(): array
    {
        $amenities = $this->amenities ?? [];
        $selected = $amenities['selected'] ?? [];
        $custom = $amenities['custom'] ?? [];

        $active = [];

        // Add predefined amenities
        foreach (self::AMENITY_OPTIONS as $category => $items) {
            foreach ($items as $key => $label) {
                if (in_array($key, $selected)) {
                    $active[] = ['key' => $key, 'label' => $label, 'category' => $category];
                }
            }
        }

        // Add custom amenities
        foreach ($custom as $customAmenity) {
            $active[] = ['key' => 'custom', 'label' => $customAmenity, 'category' => 'custom'];
        }

        return $active;
    }

    /**
     * Get coordinates as lat/lng object.
     */
    public function getCoordinates(): ?array
    {
        return $this->coordinates;
    }

    /**
     * Check if building has map coordinates.
     */
    public function hasCoordinates(): bool
    {
        $coords = $this->coordinates;

        return $coords && isset($coords['lat']) && isset($coords['lng']);
    }

    // --- INVOICE AUTOMATION HELPERS ---

    /**
     * Check if invoice automation is enabled for this building.
     */
    public function hasInvoiceAutomation(): bool
    {
        return $this->auto_generate_invoices;
    }

    /**
     * Check if invoices should be auto-sent via email.
     */
    public function shouldAutoSendInvoices(): bool
    {
        return $this->auto_generate_invoices && $this->auto_send_invoices;
    }

    /**
     * Get the day of month when invoices should be generated.
     */
    public function getInvoiceGenerationDay(): int
    {
        return $this->invoice_generation_day ?? 1;
    }

    /**
     * Check if today is the invoice generation day for this building.
     */
    public function isInvoiceGenerationDay(): bool
    {
        return $this->hasInvoiceAutomation() && now()->day === $this->getInvoiceGenerationDay();
    }
}
