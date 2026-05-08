/**
 * Bulk Operations Type Definitions
 * Interfaces for bulk operation pages and tabs
 */

import type { Building, Unit, Tenant, Lease } from './finances';

// ===== SHARED TYPES =====

// Property with buildings
export interface PropertyWithBuildings {
  id: number;
  name: string;
  buildings?: Building[];
}

// Unit with active lease (for lease operations)
export interface UnitWithLease extends Unit {
  active_lease: Lease & {
    tenant?: Tenant;
  };
}

// ===== BULK OPERATIONS INDEX PAGE =====

export interface BulkOperationsIndexPageProps {
  properties?: PropertyWithBuildings[];
  buildings?: Building[];
  units?: Unit[];
  tenants?: Tenant[];
}

// ===== LEASE MANAGEMENT TAB =====

export interface LeaseManagementTabProps {
  unitsWithLeases?: UnitWithLease[];
  selectedLeaseIds?: number[];
  buildingId?: number | null;
  wingId?: number | null;
}

// ===== RENT ADJUSTMENT TAB =====

export interface RentAdjustmentTabProps {
  unitsWithLeases?: UnitWithLease[];
  selectedLeaseIds?: number[];
  buildingId?: number | null;
  wingId?: number | null;
}

// ===== TARGET RENT TAB =====

export interface TargetRentTabProps {
  filteredUnits?: Unit[];
  selectedUnitIds?: number[];
  buildingId?: number | null;
  wingId?: number | null;
}

// ===== UNIT STATUS TAB =====

export interface UnitStatusTabProps {
  filteredUnits?: Unit[];
  selectedUnitIds?: number[];
  buildingId?: number | null;
  wingId?: number | null;
}
