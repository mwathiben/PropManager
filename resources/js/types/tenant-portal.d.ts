/**
 * Tenant Portal Type Definitions
 * Interfaces for tenant-facing pages (Tenant/*, TenantFinances/*)
 */

import type { BaseEntity, Building, Unit, Lease, Invoice, Payment, PaginatedResponse, Tenant } from './finances';
import type { ProfileUser } from './profile';
import type { TenantInvitation, PaymentVerification } from './tenants';

// ===== TENANT DASHBOARD =====

// Action item for tenant dashboard
export interface TenantActionItem {
  type: 'payment_due' | 'kyc_required' | 'document_missing' | 'ticket_open' | 'lease_expiring' | 'invitation_pending';
  title: string;
  description?: string;
  priority: 'high' | 'medium' | 'low';
  action_url?: string;
  action_label?: string;
  due_date?: string;
  amount?: number;
}

// Next payment info
export interface TenantNextPayment {
  invoice_id?: number;
  amount: number;
  due_date: string;
  is_overdue: boolean;
}

// Caretaker contact
export interface TenantCaretaker {
  id: number;
  name: string;
  email?: string;
  phone?: string;
  profile_photo_url?: string | null;
}

// Recent payment summary
export interface TenantRecentPayment {
  id: number;
  amount: number;
  payment_date: string;
  payment_method: string;
  reference?: string;
}

// Recent ticket summary
export interface TenantRecentTicket {
  id: number;
  title: string;
  status: string;
  priority: string;
  created_at: string;
}

// Pending invoice summary
export interface TenantPendingInvoice {
  id: number;
  invoice_number: string;
  total_due: number;
  balance: number;
  due_date: string;
  is_overdue: boolean;
}

// Tenant Dashboard Page Props
export interface TenantDashboardPageProps {
  hasLease: boolean;
  message?: string;
  unit?: Unit;
  building?: Building;
  lease?: Lease;
  balance?: number;
  actionItems?: TenantActionItem[];
  nextPayment?: TenantNextPayment | null;
  recentPayments?: TenantRecentPayment[];
  recentTickets?: TenantRecentTicket[];
  pendingInvoices?: TenantPendingInvoice[];
  pendingInvitations?: TenantInvitation[];
  caretaker?: TenantCaretaker | null;
}

// ===== TENANT LEASE PAGE =====

// Rent history entry
export interface TenantRentHistoryEntry {
  id: number;
  effective_date: string;
  old_amount: number;
  new_amount: number;
  reason?: string;
}

export interface TenantLeasePageProps {
  hasLease: boolean;
  lease?: Lease;
  unit?: Unit;
  building?: Building;
  rentHistory?: TenantRentHistoryEntry[];
  // Phase-84 LEASE-VISIBILITY: read-only Phase-83 lease parties + renewal.
  coTenants?: Array<{ id: number; name: string; relationship?: string | null; is_responsible_for_rent?: boolean }>;
  guarantors?: Array<{ id: number; name: string; relationship?: string | null }>;
  activeRenewal?: { id: number; status: string } | null;
  leaseAgreementId?: number | null;
}

// ===== TENANT PAYMENT REQUIRED PAGE =====

// Payment verification for tenant view
export interface TenantPaymentVerification extends BaseEntity {
  status: 'pending_payment' | 'payment_submitted' | 'payment_verified' | 'rejected';
  total_required: number;
  amount_verified: number;
  deposit_amount: number;
  first_rent_amount: number;
  rejection_reason?: string;
  submitted_at?: string;
  verified_at?: string;
}

// Landlord info for payment page
export interface TenantLandlordInfo {
  name: string;
  email?: string;
  phone?: string;
  business_name?: string;
}

// Payment Required Page Props
export interface PaymentRequiredPageProps {
  verification: TenantPaymentVerification;
  lease: Lease;
  landlord: TenantLandlordInfo;
}

// ===== TENANT COMPLETE KYC PAGE =====

// KYC requirement definition
export interface KycRequirement {
  id: number;
  type: string;
  label: string;
  description?: string;
  is_required: boolean;
  sort_order: number;
}

// KYC submission status
export interface KycSubmission {
  id: number;
  requirement_id: number;
  status: 'pending' | 'approved' | 'rejected';
  status_label: string;
  rejection_reason?: string;
  submitted_at?: string;
  document?: {
    id: number;
    file_name: string;
    file_size_formatted: string;
  };
  value?: string;
}

export interface CompleteKycPageProps {
  user: ProfileUser;
  requirements: KycRequirement[];
  submissions: KycSubmission[];
  requiredDocuments?: string[]; // Deprecated - kept for backward compatibility
}

// ===== KYC PENDING REVIEWS PAGE (LANDLORD) =====

export interface KycPendingSubmission {
  id: number;
  tenant: {
    id: number;
    name: string;
    email: string;
  };
  requirement: {
    id: number;
    type: string;
    label: string;
  };
  document?: {
    id: number;
    file_name: string;
    file_size_formatted: string;
    is_image: boolean;
    is_pdf: boolean;
  };
  value?: string;
  submitted_at: string;
}

export interface KycPendingReviewsPageProps {
  submissions: PaginatedResponse<KycPendingSubmission>;
}

// ===== TENANT NOTIFICATIONS PAGE =====

export interface TenantNotificationsPageProps {
  notifications: PaginatedResponse<{
    id: number;
    type: string;
    title: string;
    message: string;
    read_at?: string;
    created_at: string;
  }>;
}

// ===== TENANT FINANCES PAGES =====

// TenantFinances Index Page Props
export interface TenantFinancesIndexPageProps {
  hasLease: boolean;
  lease?: Lease & {
    unit?: Unit;
    building?: Building;
  };
  balance?: number;
  pendingInvoices?: TenantPendingInvoice[];
  recentPayments?: TenantRecentPayment[];
}

// TenantFinances Pay Page Props
export interface TenantFinancesPayPageProps {
  invoice: Invoice & {
    lease?: Lease;
    items?: Array<{
      id: number;
      description: string;
      amount: number;
    }>;
  };
  lease: Lease;
  paymentMethods?: string[];
  paystackPublicKey?: string;
}

// TenantInvitations Accept Page Props (for guest users accepting invite)
export interface TenantInvitationsAcceptPageProps {
  invitation: {
    token: string;
    email: string;
    landlord_name: string;
    property_name: string;
    expires_at: string;
  } | null;
  error?: string;
}

// TenantFinances History Page Props
export interface TenantFinancesHistoryPageProps {
  payments: PaginatedResponse<Payment>;
  invoices: PaginatedResponse<Invoice>;
}
