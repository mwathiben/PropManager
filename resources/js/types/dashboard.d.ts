/**
 * Dashboard Domain Type Definitions
 * Interfaces for Admin Dashboard, Caretaker Dashboard, and related entities
 */

import type { BaseEntity, Property, Building } from './finances';

// ===== ADMIN DASHBOARD TYPES =====

// System health metrics (from AdminController)
export interface SystemHealth {
  active_landlords: number;
  total_properties: number;
  total_units: number;
  total_tenants: number;
  monthly_revenue: number;
  total_revenue: number;
}

// Admin action items (from AdminController)
export interface AdminActionItems {
  inactive_landlords: number;
  new_signups: number;
}

// Landlord summary for admin list
export interface LandlordSummary extends BaseEntity {
  name: string;
  email: string;
  properties_count: number;
  units_count: number;
  occupied_units: number;
  monthly_revenue: number;
}

// Admin Dashboard page props
export interface AdminDashboardProps {
  systemHealth: SystemHealth;
  actionItems: AdminActionItems;
  landlords: LandlordSummary[];
  topLandlords: LandlordSummary[];
}

// ===== CARETAKER DASHBOARD TYPES =====

// Caretaker action items (from CaretakerController)
export interface CaretakerActionItems {
  urgent_tickets: number;
  open_tickets: number;
  pending_readings: number;
}

// Ticket statistics
export interface TicketStats {
  total: number;
  open: number;
  urgent: number;
  resolved: number;
}

// Unit statistics
export interface UnitStats {
  total: number;
  occupied: number;
  vacant: number;
  maintenance: number;
}

// Task priority type
export type TaskPriority = 'urgent' | 'high' | 'normal' | 'low';

// Caretaker task (ticket) for today's tasks list
export interface CaretakerTask extends BaseEntity {
  title: string;
  description?: string;
  priority: TaskPriority;
  status: string;
  unit?: {
    unit_number: string;
  };
  building?: {
    name: string;
  };
}

// Landlord contact info for caretaker
export interface LandlordContact {
  id: number;
  name: string;
  email?: string;
  mobile_number?: string;
}

// Property info for caretaker (minimal shape)
export interface CaretakerProperty {
  name: string;
}

// Building info for caretaker (minimal shape)
export interface CaretakerBuilding {
  id: number;
  name: string;
}

// Caretaker Dashboard page props
export interface CaretakerDashboardProps {
  property: CaretakerProperty | null;
  buildings: CaretakerBuilding[];
  actionItems: CaretakerActionItems;
  ticketStats: TicketStats;
  localTodaysTasks: CaretakerTask[];
  unitStats: UnitStats;
  hasWaterEnabled: boolean;
  landlord: LandlordContact | null;
}

// ===== LANDLORD DASHBOARD TYPES =====

// Unit status type
export type DashboardUnitStatus = 'vacant' | 'occupied' | 'maintenance' | 'arrears';

// Dashboard Unit (minimal unit shape for grid display)
export interface DashboardUnit extends BaseEntity {
  unit_number: string;
  floor_number?: number;
  unit_type?: string;
  status: DashboardUnitStatus;
  target_rent?: number;
  building_id?: number;
  wing_name?: string;
  building?: {
    name: string;
    unit_prefix?: string;
  };
  active_lease?: {
    id: number;
    tenant?: {
      id: number;
      name: string;
      email?: string;
    };
  };
}

// Dashboard Wing (building wing for multi-wing properties)
export interface DashboardWing extends BaseEntity {
  name: string;
  unit_prefix?: string;
  units?: DashboardUnit[];
}

// Dashboard Property (with nested buildings)
export interface DashboardProperty extends BaseEntity {
  name: string;
  address?: string;
  buildings: DashboardBuilding[];
}

// Dashboard Building (extended from base Building)
export interface DashboardBuilding extends BaseEntity {
  name: string;
  property_id: number;
  floors?: number;
  units_per_floor?: number;
  unit_prefix?: string;
}

// Landlord action items (from DashboardController)
export interface LandlordActionItems {
  overdue_invoices: number;
  overdue_amount: number;
  expiring_leases: number;
  urgent_tickets: number;
  open_tickets: number;
  vacant_units: number;
  pending_readings: number;
}

// Financial metrics (from DashboardController)
export interface FinancialMetrics {
  monthly_revenue: number;
  expected_revenue: number;
  collection_rate: number;
  total_arrears: number;
  total_deposits?: number;
}

// Arrears aging buckets
export interface ArrearsAging {
  '0_30': number;
  '31_60': number;
  '61_90': number;
  '90_plus': number;
}

// Dashboard stats (occupancy)
export interface DashboardStats {
  total_units: number;
  occupied_units: number;
  vacant_units: number;
  occupancy_rate: number;
}

// Tenant KYC stats
export interface TenantKycStats {
  total: number;
  complete: number;
  incomplete: number;
  rate: number;
}

// Recent payment (for dashboard feed)
export interface DashboardPayment extends BaseEntity {
  amount: number;
  payment_method: string;
  payment_date: string;
  platform_fee?: number | null;
  landlord_amount?: number | null;
  split_provider?: string | null;
  lease_state?: 'active' | 'ended' | 'soft_deleted' | 'unknown';
  invoice?: {
    id: number;
    lease?: {
      tenant?: {
        name: string;
      };
      unit?: {
        unit_number: string;
      };
    };
  };
}

// Recent ticket (for dashboard feed)
export interface DashboardTicket extends BaseEntity {
  title: string;
  status: string;
  priority: string;
  unit_number?: string;
  reporter_name?: string;
}

// Expiring lease (for dashboard feed)
export interface ExpiringLease extends BaseEntity {
  end_date: string;
  tenant?: {
    name: string;
  };
  unit?: {
    id: number;
    unit_number: string;
    building?: {
      name: string;
    };
  };
}

// Units grouped by wing (for multi-wing display)
export interface UnitsByWing {
  wing: DashboardWing;
  units: DashboardUnit[];
}

// Platform fee tier
export interface PlatformFeeTier {
  id: number;
  name: string;
  min_volume: number;
  max_volume: number | null;
  fee_percentage: number;
  sort_order: number;
}

// Landlord Dashboard page props
export interface DashboardPageProps {
  properties: DashboardProperty[];
  property: DashboardProperty;
  buildings: DashboardBuilding[];
  activeBuilding: DashboardBuilding;
  allBuildingsMode?: boolean;
  dashboardScope?: 'active_building' | 'all_buildings';
  wings: DashboardWing[];
  hasWings: boolean;
  activeWingId?: number | null;
  activeFloor?: number | null;
  allFloors: number[];
  units: DashboardUnit[];
  allUnits: DashboardUnit[];
  unitsByWing: UnitsByWing[];
  actionItems: LandlordActionItems;
  financialMetrics: FinancialMetrics;
  arrearsAging: ArrearsAging;
  stats: DashboardStats;
  recentPayments: DashboardPayment[];
  recentTickets: DashboardTicket[];
  expiringLeases: ExpiringLease[];
  tenantKycStats?: TenantKycStats;
  currentTier?: PlatformFeeTier | null;
  mtdVolume?: number;
  allTiers?: PlatformFeeTier[];
  // Phase-36 INSIGHT-LANDLORD-1: composite growth signals.
  growth?: LandlordGrowthSummary | null;
  // Phase-55 WIDGET-ORDERING: landlord-preferred ordering of bottom-row widgets.
  widgetOrder?: Array<'recent-payments' | 'recent-tickets' | 'expiring-leases'>;
}

export interface LandlordGrowthSummary {
  engagement_score: number;
  engagement_score_delta_7d: number;
  engagement_components: Record<string, number>;
  referral_count_30d: number;
  current_plan_slug: string | null;
  usage_ratios: Array<{ feature: string; usage: number; limit: number; ratio: number }>;
}

// ===== BUILDINGS DASHBOARD TYPES =====

// Period comparison data
export interface PeriodComparison {
  current: {
    revenue: number;
    expenses: number;
    net: number;
  };
  previous: {
    revenue: number;
    expenses: number;
    net: number;
  };
  change: {
    revenue: number;
    expenses: number;
    net: number;
  };
}

// Buildings Dashboard page props
export interface BuildingsDashboardPageProps {
  property: DashboardProperty;
  buildings: DashboardBuilding[];
  activeBuilding: DashboardBuilding;
  units: DashboardUnit[];
  actionItems: LandlordActionItems;
  financialMetrics: FinancialMetrics;
  periodComparison?: PeriodComparison;
  arrearsAging: ArrearsAging;
  stats: DashboardStats;
  recentPayments: DashboardPayment[];
  recentTickets: DashboardTicket[];
  expiringLeases: ExpiringLease[];
  filters?: {
    period?: string;
    status?: string;
    floor?: number;
    unit_type?: string;
  };
  availableFloors?: number[];
  availableUnitTypes?: string[];
}

// ===== ADMIN PAGE TYPES =====

// Admin User
export interface AdminUser extends BaseEntity {
  name: string;
  email: string;
  role: string;
  email_verified_at?: string;
  last_login_at?: string;
  is_active: boolean;
  properties_count?: number;
  units_count?: number;
}

// Admin Users Page Props
export interface AdminUsersPageProps {
  users: {
    data: AdminUser[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
  filters: {
    search?: string;
    role?: string;
  };
  roles: Record<string, string>;
}

// Billing Settings
export interface BillingSettings {
  active_billing_model: string;
  transaction_fee_percentage: number;
  minimum_fee: number;
  maximum_fee?: number;
  fee_bearer: string;
}

// Billing Change Record
export interface BillingChange extends BaseEntity {
  field: string;
  old_value: string;
  new_value: string;
  reason?: string;
  changed_by?: {
    name: string;
  };
}

// Monthly Billing Analytics
export interface MonthlyBillingAnalytics {
  total_transactions: number;
  total_fees_collected: number;
  total_volume: number;
  by_model: Record<string, number>;
}

// Admin Billing Settings Page Props
export interface AdminBillingSettingsPageProps {
  settings: BillingSettings;
  billingModels: Record<string, string>;
  feeBearers: Record<string, string>;
  recentChanges: BillingChange[];
  monthlyAnalytics: MonthlyBillingAnalytics;
}

// Admin Settings Page Props
export interface AdminSettingsPageProps {
  paymentSettings: {
    paystack_configured: boolean;
    mpesa_configured: boolean;
  };
}

// Audit Log Entry
export interface AuditLogEntry extends BaseEntity {
  log_name: string;
  description: string;
  subject_type?: string;
  subject_id?: number;
  causer_type?: string;
  causer_id?: number;
  event?: string;
  event_color?: string;
  properties?: {
    old?: Record<string, unknown>;
    attributes?: Record<string, unknown>;
  };
  causer?: {
    name: string;
    email: string;
  };
}

// Admin Audit Logs Page Props
// Phase-20 FRONT-UX-1: cursor paginator shape (was offset).
export interface AdminAuditLogsPageProps {
  logs: {
    data: AuditLogEntry[];
    per_page: number;
    next_page_url: string | null;
    prev_page_url: string | null;
    path?: string;
  };
  filters: {
    event_type?: string;
    model_type?: string;
    date_from?: string;
    date_to?: string;
    search?: string;
  };
  eventTypes: Array<{ value: string; label: string }>;
  modelTypes: Array<{ value: string; label: string }>;
}

// Admin Audit Log Detail Page Props
export interface AdminAuditLogDetailPageProps {
  log: AuditLogEntry;
}

// Admin Landlords Page Props
export interface AdminLandlordsPageProps {
  landlords: {
    data: LandlordSummary[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
  filters: {
    search?: string;
  };
}
