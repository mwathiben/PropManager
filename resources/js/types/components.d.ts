/**
 * Shared Components Type Definitions
 * Interfaces for reusable Vue component props
 */

import type { BaseEntity, Lease, Invoice, Payment, Tenant, Building } from './finances';
import type { PaginatedResponse } from './global';

// ===== BUILDING MAP COMPONENT =====

// Map coordinates
export interface MapCoordinates {
  lat: number;
  lng: number;
}

// BuildingMap component props
export interface BuildingMapProps {
  coordinates: MapCoordinates | null;
  address?: string;
  editable?: boolean;
  height?: string;
}

// ===== FINANCIAL SUMMARY CARD COMPONENT =====

// Financial summary data
export interface FinancialSummary {
  total_paid: number;
  total_invoiced: number;
  outstanding: number;
  wallet_balance: number;
  deposit_held: number;
}

// FinancialSummaryCard component props
export interface FinancialSummaryCardProps {
  summary: FinancialSummary;
  compact?: boolean;
}

// ===== TENANT PROFILE MODAL COMPONENT =====

// Emergency contact
export interface EmergencyContact {
  name: string;
  phone: string;
  relationship: string;
}

// Tenant note
export interface TenantNote extends BaseEntity {
  content: string;
}

// Document reference
export interface DocumentReference {
  id: number;
  type: string;
  filename: string;
  uploaded_at: string;
}

// Activity log entry
export interface ActivityLogEntry {
  type: string;
  description: string;
  created_at: string;
}

// Verification status
export interface VerificationStatus {
  kyc_completed: boolean;
  kyc_completed_at?: string;
}

// Tenant profile modal data (returned from /tenants/{id}/modal-data)
export interface TenantProfileModalData {
  tenant: Tenant & {
    profile_photo_path?: string;
    profile_photo_url?: string | null;
    mobile_number?: string;
  };
  activeLease?: Lease & {
    unit?: {
      unit_number: string;
      building?: {
        name: string;
      };
    };
  };
  pastLeases?: Lease[];
  financialSummary?: FinancialSummary;
  documents?: DocumentReference[];
  payments?: Payment[];
  invoices?: Invoice[];
  emergencyContacts?: EmergencyContact[];
  tenantNotes?: TenantNote[];
  verificationStatus?: VerificationStatus;
  activities?: ActivityLogEntry[];
}

// TenantProfileModal component props
export interface TenantProfileModalProps {
  show: boolean;
  tenantId: number | null;
  initialData?: TenantProfileModalData | null;
}

// ===== INVOICES INDEX PAGE =====

// Invoice filters
export interface InvoiceFilters {
  search?: string;
  status?: string;
  building_id?: string | number;
}

// Building reference for filter dropdown
export interface BuildingReference {
  id: number;
  name: string;
}

// Invoices Index page props
export interface InvoicesIndexPageProps {
  invoices: PaginatedResponse<Invoice>;
  buildings: BuildingReference[];
  filters: InvoiceFilters;
}

// ===== ADD BUILDING MODAL COMPONENT =====

// Building types lookup (key -> display name)
export type BuildingTypesLookup = Record<string, string>;

// Amenity options lookup (key -> display name)
export type AmenityOptionsLookup = Record<string, string>;

// AddBuildingModal component props
export interface AddBuildingModalProps {
  show: boolean;
  buildingTypes: BuildingTypesLookup;
  amenityOptions: AmenityOptionsLookup;
}

// ===== TENANT PROFILE OVERVIEW TAB COMPONENT =====

// TenantProfile OverviewTab component props
export interface TenantOverviewTabProps {
  tenant: Tenant & {
    profile_photo_url?: string | null;
    mobile_number?: string;
  };
  activeLease?: Lease & {
    unit?: {
      unit_number: string;
      building?: {
        name: string;
      };
    };
    rent_amount: number;
    deposit_amount: number;
    start_date: string;
  } | null;
  financialSummary?: FinancialSummary;
  verificationStatus?: VerificationStatus;
  emergencyContacts?: EmergencyContact[];
  activities?: ActivityLogEntry[];
}
