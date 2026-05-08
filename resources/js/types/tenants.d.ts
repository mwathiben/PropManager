/**
 * Tenants Domain Type Definitions
 * Interfaces for Tenant management, invitations, verifications, and move-outs
 */

import type { BaseEntity, Building, Unit, Tenant, PaginatedResponse } from './finances';

// Tenant Invitation Status
export type TenantInvitationStatus = 'pending' | 'accepted' | 'expired' | 'cancelled';

// Verification Status
export type VerificationStatus = 'pending' | 'in_progress' | 'approved' | 'rejected';

// Payment Verification Status
export type PaymentVerificationStatus = 'pending' | 'verified' | 'rejected';

// Move-Out Status
export type MoveOutStatus = 'scheduled' | 'in_progress' | 'completed' | 'cancelled';

// Tenant Invitation (for tenant onboarding)
export interface TenantInvitation extends BaseEntity {
  landlord_id: number;
  email: string;
  name?: string;
  phone?: string;
  unit_id?: number;
  unit?: Unit;
  building_id?: number;
  building?: Building;
  status: TenantInvitationStatus;
  token: string;
  expires_at: string;
  accepted_at?: string;
  lease_start_date?: string;
  rent_amount?: number;
  deposit_amount?: number;
}

// Verification (KYC/document verification)
export interface Verification extends BaseEntity {
  tenant_id: number;
  tenant?: Tenant;
  unit?: Unit;
  type: string;
  status: VerificationStatus;
  submitted_at?: string;
  verified_at?: string;
  verified_by?: number;
  notes?: string;
  documents?: {
    id: number;
    name: string;
    url: string;
  }[];
}

// Payment Verification (manual payment proof verification)
export interface PaymentVerification extends BaseEntity {
  tenant_id: number;
  tenant?: Tenant;
  lease_id?: number;
  unit?: Unit;
  amount: number;
  payment_method: string;
  reference?: string;
  proof_url?: string;
  status: PaymentVerificationStatus;
  submitted_at: string;
  verified_at?: string;
  verified_by?: number;
  rejection_reason?: string;
}

// Move-Out Request
export interface MoveOut extends BaseEntity {
  lease_id: number;
  tenant_id: number;
  tenant?: Tenant;
  unit?: Unit;
  building?: Building;
  status: MoveOutStatus;
  requested_date: string;
  scheduled_date?: string;
  completed_date?: string;
  reason?: string;
  deposit_refund_amount?: number;
  deductions?: number;
  notes?: string;
}

// Past Tenant (archived tenant record)
export interface PastTenant extends BaseEntity {
  name: string;
  email: string;
  phone?: string;
  unit?: Unit;
  building?: Building;
  lease_start: string;
  lease_end: string;
  total_paid?: number;
  deposit_refunded?: number;
  move_out_reason?: string;
}

// Tenant Stats (for hub overview)
export interface TenantStats {
  total: number;
  active: number;
  pending_invitations: number;
  pending_verifications: number;
  pending_payment_verifications: number;
  scheduled_move_outs: number;
}

// Tenant Filters
export interface TenantFilters {
  search?: string;
  building_id?: number | string | null;
  unit_id?: number | string | null;
  status?: string;
}

// Tenants Hub Counts (for tab badges)
export interface TenantsHubCounts {
  pendingInvitations: number;
  pendingVerifications: number;
  paymentVerifications: number;
  moveOuts: number;
}

// Tenants Hub Page Props
export interface TenantsHubPageProps {
  activeTab: string;
  tenants: PaginatedResponse<Tenant>;
  invitations: PaginatedResponse<TenantInvitation>;
  verifications: PaginatedResponse<Verification>;
  paymentVerifications: PaginatedResponse<PaymentVerification>;
  moveOuts: PaginatedResponse<MoveOut>;
  pastTenants: PaginatedResponse<PastTenant>;
  stats: TenantStats;
  filters: TenantFilters;
  buildings: Building[];
  units: Unit[];
  counts: TenantsHubCounts;
}

// Directory Tab Props
export interface TenantsDirectoryTabProps {
  tenants: PaginatedResponse<Tenant>;
  filters: TenantFilters;
  buildings: Building[];
}

// Onboarding Tab Props
export interface TenantsOnboardingTabProps {
  invitations: PaginatedResponse<TenantInvitation>;
  filters: TenantFilters;
  buildings: Building[];
  units: Unit[];
}

// Verifications Tab Props
export interface TenantsVerificationsTabProps {
  verifications: PaginatedResponse<Verification>;
  filters: TenantFilters;
}

// Payment Verifications Tab Props
export interface TenantsPaymentVerificationsTabProps {
  paymentVerifications: PaginatedResponse<PaymentVerification>;
  filters: TenantFilters;
}

// Move-Outs Tab Props
export interface TenantsMoveOutsTabProps {
  moveOuts: PaginatedResponse<MoveOut>;
  filters: TenantFilters;
}

// History Tab Props
export interface TenantsHistoryTabProps {
  pastTenants: PaginatedResponse<PastTenant>;
  filters: TenantFilters;
}

// ===== INVITATION PAGE PROPS =====

// Invitation Accept Page Invitation Data
export interface InvitationAcceptData {
  token: string;
  email: string;
  landlord_name: string;
  property_name: string;
  expires_at: string;
}

// Invitation Accept Page Props (guest page for new users)
export interface InvitationAcceptPageProps {
  invitation: InvitationAcceptData | null;
  error?: string;
}

// Invitation Accept Existing Page Props (authenticated user)
export interface InvitationAcceptExistingPageProps {
  invitation: {
    id: number;
    landlord_name: string;
    property_name: string;
    expires_at: string;
  };
}

// ===== VERIFICATION PAGE PROPS =====

// Verification Template Item
export interface VerificationTemplateItem {
  id: number;
  name: string;
  description?: string;
  is_required: boolean;
  document_type?: string;
}

// Verification Template
export interface VerificationTemplate {
  id: number;
  name: string;
  is_default: boolean;
  items?: VerificationTemplateItem[];
}

// Lease Verification Item
export interface LeaseVerificationItem {
  id: number;
  item?: VerificationTemplateItem;
  status: 'pending' | 'verified' | 'rejected' | 'waived';
  notes?: string;
  verified_at?: string;
  verifier?: {
    name: string;
  };
}

// Verification Conduct Lease
export interface VerificationConductLease {
  id: number;
  tenant?: Tenant;
  unit?: Unit;
  verifications?: LeaseVerificationItem[];
}

// Verifications Conduct Page Props
export interface VerificationsConductPageProps {
  lease: VerificationConductLease;
  templates: VerificationTemplate[];
  defaultTemplate?: VerificationTemplate;
  hasVerifications: boolean;
  progress: number;
}

// ===== COMPONENT PROPS =====

// Tenant Lease Finances Tab Props (TenantProfile component)
export interface TenantLeaseFinancesTabProps {
  activeLease: {
    unit?: Unit;
    rent_amount: number;
    deposit_amount: number;
    start_date: string;
    wallet_balance?: number;
    arrears?: number;
    rent_history?: Array<{
      id: number;
      effective_date: string;
      old_amount: number;
      new_amount: number;
    }>;
  } | null;
  pastLeases: Array<{
    id: number;
    unit?: Unit;
    rent_amount: number;
    start_date: string;
    end_date?: string;
    is_active: boolean;
  }>;
  financialSummary: {
    total_paid?: number;
    total_due?: number;
    balance?: number;
    last_payment_date?: string;
  };
}

// ===== PAYMENT VERIFICATIONS PAGE PROPS =====

// Payment Verification for list display
export interface PaymentVerificationListItem extends PaymentVerification {
  building?: Building;
}

// Payment Verifications Index Filters
export interface PaymentVerificationsFilters {
  search?: string;
  status?: string;
  building_id?: number | string | null;
}

// Payment Verifications Index Page Props
export interface PaymentVerificationsIndexPageProps {
  verifications: PaginatedResponse<PaymentVerificationListItem>;
  buildings: Building[];
  filters: PaymentVerificationsFilters;
}

// Payment Verifications Show Page Props
export interface PaymentVerificationsShowPageProps {
  verification: PaymentVerification & {
    tenant: Tenant;
    unit?: Unit;
    building?: Building;
    proof_documents?: Array<{
      id: number;
      filename: string;
      url: string;
    }>;
  };
}

// ===== LANDLORD PAGE PROPS =====

// Property with buildings for Landlord Home
export interface PropertyWithBuildings {
  id: number;
  name: string;
  address?: string;
  buildings: Array<{
    id: number;
    name: string;
    units_count: number;
    occupied_units_count: number;
  }>;
}

// Landlord Home Page Props
export interface LandlordHomePageProps {
  properties: PropertyWithBuildings[];
  buildingTypes: Record<string, string>;
}
