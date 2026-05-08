/**
 * Water Domain Type Definitions
 * Interfaces for WaterReading, WaterSettings, and related entities
 */

import type { BaseEntity, Building, Unit, PaginatedResponse } from './finances';

// Water Reading Status
export type WaterReadingStatus = 'pending' | 'approved' | 'rejected' | 'invoiced';

// Water Reading
export interface WaterReading extends BaseEntity {
  unit_id: number;
  landlord_id: number;
  previous_reading: number;
  current_reading: number;
  consumption: number;
  rate: number;
  cost: number;
  reading_date: string;
  status: WaterReadingStatus;
  is_invoiced: boolean;
  invoiced_at?: string;
  notes?: string;
  recorded_by?: number;
  approved_by?: number;
  approved_at?: string;
  rejected_reason?: string;
  unit?: Unit & {
    building?: Building;
    active_lease?: {
      tenant?: {
        name: string;
      };
    };
  };
}

// Water Stats
export interface WaterStats {
  total_readings: number;
  pending_readings: number;
  approved_readings: number;
  rejected_readings: number;
  total_consumption: number;
  total_billed: number;
  avg_consumption?: number;
  readings_this_month?: number;
}

// Water Settings (landlord-level)
export interface WaterSettings {
  water_billing_enabled: boolean;
  water_unit_rate: number;
  water_billing_day?: number;
  include_water_in_invoice: boolean;
  require_approval: boolean;
  auto_approve_threshold?: number;
}

// Building Water Settings (building-level override)
export interface BuildingWaterSettings {
  building_id: number;
  water_unit_rate?: number;
  water_billing_enabled?: boolean;
}

// Water Rate History Entry
export interface WaterRateHistoryEntry extends BaseEntity {
  landlord_id: number;
  building_id?: number;
  rate: number;
  effective_from: string;
  effective_until?: string;
}

// Water Filters
export interface WaterFilters {
  search?: string;
  status?: WaterReadingStatus | '';
  building_id?: number | string | null;
  wing_id?: number | string | null;
  date_from?: string;
  date_to?: string;
  is_invoiced?: boolean | null;
}

// Water Hub Page Props
export interface WaterHubPageProps {
  activeTab: string;
  buildings: Building[];
  readings: PaginatedResponse<WaterReading>;
  stats: WaterStats;
  filters: WaterFilters;
  settings: WaterSettings;
  rateHistory?: WaterRateHistoryEntry[];
}

// Water Readings Tab Props
export interface WaterReadingsTabProps {
  buildings: Building[];
  readings?: PaginatedResponse<WaterReading>;
  filters: WaterFilters;
}

// Water History Tab Props
export interface WaterHistoryTabProps {
  readings: PaginatedResponse<WaterReading>;
  buildings: Building[];
  buildingsList?: Building[];
  filters: WaterFilters;
}

// Water Settings Tab Props
export interface WaterSettingsTabProps {
  settings: WaterSettings;
  rateHistory?: WaterRateHistoryEntry[];
}

// Readings Index Page Props (meter reading input)
export interface ReadingsIndexPageProps {
  buildings: (Building & {
    units: (Unit & {
      previous_reading: number | null;
    })[];
  })[];
}

// Readings Review Page Props
export interface ReadingsReviewPageProps {
  pendingReadings: PaginatedResponse<WaterReading>;
  buildings: Building[];
  filters: WaterFilters;
}

// Readings History Page Props
export interface ReadingsHistoryPageProps {
  readings: PaginatedResponse<WaterReading>;
  filters: WaterFilters;
  buildings: Building[];
}

// Water Settings Page Props (global settings page)
export interface WaterSettingsPageProps {
  buildings: (Building & {
    water_billing_type?: string | null;
    water_unit_rate?: number | null;
    water_flat_rate?: number | null;
  })[];
  globalSettings: WaterSettings & {
    water_billing_type?: string;
    flat_water_rate?: number;
  };
}

// Water Readings Input Tab Props (for submitting new readings)
export interface WaterReadingsInputTabProps {
  buildings: (Building & {
    units?: (Unit & {
      previous_reading?: number | null;
      active_lease?: {
        tenant?: {
          name: string;
        };
      };
    })[];
  })[];
  buildingsData?: (Building & {
    units?: (Unit & {
      previous_reading?: number | null;
    })[];
  })[];
}

// Buildings Water Settings Page Props
export interface BuildingsWaterSettingsPageProps {
  building: Building & {
    water_billing_type?: string | null;
    water_flat_rate?: number | null;
    water_unit_rate?: number | null;
  };
}

// Building Show Page Props
export interface BuildingsShowPageProps {
  building: Building & {
    floors?: number;
    units_per_floor?: number;
    location?: string;
    google_maps_url?: string;
    latitude?: number;
    longitude?: number;
    amenities?: string[];
    images?: string[];
    building_type?: string;
  };
  property: {
    id: number;
    name: string;
  };
  siblingBuildings?: Building[];
  unitStats: {
    total: number;
    occupied: number;
    vacant: number;
    maintenance: number;
    arrears: number;
  };
  buildingTypes: Record<string, string>;
  amenityOptions: Record<string, string>;
  activeAmenities: string[];
}
