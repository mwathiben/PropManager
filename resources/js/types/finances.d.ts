/**
 * Finance Hub Type Definitions
 * Interfaces for Invoice, Payment, Refund, Lease, and related entities
 */

// Base entity with common fields
export interface BaseEntity {
  id: number;
  created_at?: string;
  updated_at?: string;
}

// Invoice Status (matches App\Enums\InvoiceStatus)
export type InvoiceStatus = 'draft' | 'sent' | 'viewed' | 'partial' | 'paid' | 'overdue' | 'voided' | 'cancelled';

// Payment Method (canonical 4 methods - matches App\Enums\PaymentMethod)
export type PaymentMethod = 'cash' | 'bank_transfer' | 'mobile_money' | 'paystack';

// Refund Status
export type RefundStatus = 'pending' | 'approved' | 'processing' | 'completed' | 'failed' | 'cancelled';

// Unit Status
export type UnitStatus = 'vacant' | 'occupied' | 'maintenance' | 'arrears';

// Invoice Item Type
export type InvoiceItemType = 'rent' | 'deposit' | 'water' | 'electricity' | 'arrears' | 'late_fee' | 'admin_fee' | 'key_deposit' | 'other' | 'credit';

// Building
export interface Building extends BaseEntity {
  name: string;
  property_id: number;
  property?: Property;
  floors?: number;
  units_per_floor?: number;
}

// Property
export interface Property extends BaseEntity {
  name: string;
  address?: string;
  landlord_id: number;
}

// Unit
export interface Unit extends BaseEntity {
  unit_number: string;
  building_id: number;
  building?: Building;
  status: UnitStatus;
  target_rent?: number;
  floor?: number;
}

// Tenant (User with tenant role)
export interface Tenant extends BaseEntity {
  name: string;
  email: string;
  phone?: string;
  national_id?: string;
}

// Lease
export interface Lease extends BaseEntity {
  unit_id: number;
  tenant_id: number;
  unit?: Unit;
  tenant?: Tenant;
  rent_amount: number;
  deposit_amount: number;
  start_date: string;
  end_date?: string;
  is_active: boolean;
  wallet_balance?: number;
}

// Invoice Item (line item on invoice)
export interface InvoiceItem extends BaseEntity {
  invoice_id: number;
  item_type: InvoiceItemType;
  description: string;
  quantity: number;
  unit_price: number;
  total: number;
  sort_order?: number;
  metadata?: Record<string, unknown>;
}

// Invoice
export interface Invoice extends BaseEntity {
  invoice_number: string;
  lease_id: number;
  landlord_id: number;
  lease?: Lease;
  invoice_type_id?: number;
  template_id?: number;
  status: InvoiceStatus;
  total_due: number;
  amount_paid: number;
  balance?: number;
  due_date: string;
  period_start?: string;
  period_end?: string;
  notes?: string;
  sent_at?: string;
  viewed_at?: string;
  items?: InvoiceItem[];
}

// Receipt
export interface Receipt extends BaseEntity {
  payment_id: number;
  invoice_id?: number;
  lease_id: number;
  landlord_id: number;
  receipt_number: string;
  amount: number;
  payment_method: PaymentMethod;
  reference?: string;
  notes?: string;
  is_partial: boolean;
  issued_at: string;
  emailed_at?: string;
  pdf_path?: string;
}

// Payment
export interface Payment extends BaseEntity {
  invoice_id?: number;
  lease_id: number;
  landlord_id: number;
  invoice?: Invoice;
  lease?: Lease;
  receipt?: Receipt;
  amount: number;
  payment_method: PaymentMethod;
  reference?: string;
  notes?: string;
  status?: string;
  payment_date?: string;
  mpesa_transaction_id?: string;
  mpesa_checkout_request_id?: string;
}

// Refund
export interface Refund extends BaseEntity {
  payment_id: number;
  lease_id?: number;
  landlord_id: number;
  payment?: Payment;
  lease?: Lease;
  amount: number;
  reason: string;
  status: RefundStatus;
  refund_method?: PaymentMethod;
  processed_at?: string;
  processed_by?: number;
  notes?: string;
}

// Deposit (security deposit tracking)
export interface Deposit extends BaseEntity {
  lease_id: number;
  landlord_id: number;
  lease?: Lease;
  amount: number;
  amount_held: number;
  status: 'held' | 'partially_refunded' | 'refunded' | 'forfeited';
  refund_amount?: number;
  forfeit_amount?: number;
  forfeit_reason?: string;
  refunded_at?: string;
}

// Expense
export interface Expense extends BaseEntity {
  landlord_id: number;
  property_id?: number;
  building_id?: number;
  unit_id?: number;
  category: string;
  vendor?: string;
  description: string;
  amount: number;
  expense_date: string;
  receipt_path?: string;
  is_recurring: boolean;
}

// Credit Note
export interface CreditNote extends BaseEntity {
  lease_id: number;
  landlord_id: number;
  invoice_id?: number;
  lease?: Lease;
  credit_note_number: string;
  amount: number;
  reason: string;
  status: 'pending' | 'approved' | 'applied' | 'void';
  approved_by?: number;
  applied_at?: string;
}

// Laravel Pagination (re-exported from global)
export type { PaginationLink, PaginationMeta, PaginatedResponse } from '@/types/global';

// DataTable Column Definition
export interface ColumnDefinition {
  key: string;
  label: string;
  sortable?: boolean;
  align?: 'left' | 'center' | 'right';
  width?: string;
}

// Sort State
export interface SortState {
  key: string;
  direction: 'asc' | 'desc';
}

// Filter State (used across tabs)
export interface FilterState {
  search?: string;
  status?: string;
  buildingId?: number | null;
  paymentMethod?: string;
  dateFrom?: string;
  dateTo?: string;
  [key: string]: unknown;
}

// Overview Stats
export interface FinanceStats {
  this_month: number;
  last_month: number;
  month_trend: number;
  pending_amount: number;
  overdue_amount: number;
  overdue_count: number;
  collection_rate: number;
  total_arrears: number;
  total_deposits: number;
}

// Monthly Trend Data Point
export interface TrendDataPoint {
  month: string;
  revenue: number;
  expenses: number;
  net: number;
}

// Metric Card Props
export interface MetricCardProps {
  title: string;
  value: number | string;
  format?: 'currency' | 'number' | 'percent';
  subtitle?: string | null;
  trend?: {
    direction: 'up' | 'down';
    value: string;
  } | null;
  icon?: object;
  color?: 'emerald' | 'blue' | 'yellow' | 'red' | 'gray';
}

// Modal State (for Pinia store)
export interface ModalState<T = Record<string, unknown>> {
  show: boolean;
  data?: T;
}

export interface RecordPaymentModalData {
  invoiceId?: number;
  leaseId?: number;
}

export interface InvoiceDetailModalData {
  id: number;
}

export interface PaymentDetailModalData {
  id: number;
}

// Form Types
export interface PaymentForm {
  invoice_id: number | null;
  amount: number | string;
  payment_method: PaymentMethod;
  payment_date: string;
  reference: string;
  notes: string;
}

export interface RefundForm {
  payment_id: number;
  amount: number | string;
  reason: string;
  refund_method: PaymentMethod;
  notes: string;
}

// Export Format
export type ExportFormat = 'xlsx' | 'pdf' | 'csv';

// Filter Config (for useTabFilters composable)
export interface FilterConfigItem {
  default: unknown;
  urlKey?: string;
  type?: 'dateRange' | 'string' | 'number';
}

export interface FilterConfig {
  [key: string]: FilterConfigItem;
}

// Composable Return Types
export interface UseFormattersReturn {
  formatMoney: (value: number | null | undefined, opts?: { maximumFractionDigits?: number; currency?: string }) => string;
  formatCurrency: (value: number | null | undefined, opts?: { maximumFractionDigits?: number; currency?: string }) => string;
  formatDate: (date: string | Date | null | undefined, format?: 'short' | 'long' | 'numeric') => string;
  formatDateTime: (date: string | Date | null | undefined) => string;
  formatRelativeDate: (date: string | Date | null | undefined) => string;
  formatRelativeTime: (date: string | Date | null | undefined) => string;
  formatPercent: (value: number | null | undefined, decimals?: number) => string;
  formatNumber: (value: number | null | undefined) => string;
  formatFileSize: (bytes: number | null | undefined) => string;
}

export interface UsePaymentsReturn {
  isProcessing: import('vue').Ref<boolean>;
  error: import('vue').Ref<string | null>;
  paymentMethods: Record<string, { label: string; icon: string }>;
  getPaymentMethodLabel: (method: string) => string;
  getPaymentMethodIcon: (method: string) => string;
  invoiceStatuses: Record<string, { label: string; description: string }>;
  getInvoiceStatusLabel: (status: string) => string;
  refundStatuses: Record<string, { label: string; description: string }>;
  getRefundStatusLabel: (status: string) => string;
  calculatePaymentProgress: (invoice: Invoice) => number;
  calculateBalance: (invoice: Invoice) => number;
  isFullyPaid: (invoice: Invoice) => boolean;
  isOverdue: (invoice: Invoice) => boolean;
  recordManualPayment: (data: PaymentForm) => Promise<void>;
}

// ===== LEASE PAGE TYPES =====

// Lease Stats
export interface LeaseStats {
  total: number;
  active: number;
  expiring_soon: number;
  expired: number;
}

// Leases Index Page Props
export interface LeasesIndexPageProps {
  leases: PaginatedResponse<Lease>;
  stats: LeaseStats;
  buildings: Building[];
  filters: {
    search?: string;
    status?: string;
    building_id?: number | string | null;
  };
}

// Leases Create Page Props
export interface LeasesCreatePageProps {
  unit: Unit & {
    target_rent?: number;
    building?: Building;
  };
  smsConfigured: boolean;
  whatsappConfigured: boolean;
}

// ===== TENANT PAGE TYPES =====

// Tenant Counts
export interface TenantCounts {
  active: number;
  pending: number;
  past: number;
}

// Tenant Stats
export interface TenantStatsData {
  total_active: number;
  pending_invitations: number;
  pending_verifications: number;
  arrears_count: number;
  total_arrears: number;
}

// Tenants Index Page Props
export interface TenantsIndexPageProps {
  activeTenants: PaginatedResponse<Tenant>;
  pastTenants: PaginatedResponse<Tenant>;
  pendingInvitations: PaginatedResponse<unknown>;
  tab: string;
  counts: TenantCounts;
  stats: TenantStatsData;
  filters: {
    search?: string;
  };
}

// Tenant Show Page Props
export interface TenantShowPageProps {
  tenant: Tenant & {
    notes?: Array<{
      id: number;
      content: string;
      created_at: string;
      user?: { name: string };
    }>;
    emergency_contacts?: Array<{
      id: number;
      name: string;
      phone: string;
      relationship: string;
    }>;
  };
  activeLease: Lease | null;
  payments: Payment[];
  invoices: Invoice[];
  verificationTemplates: Array<{ id: number; name: string }>;
  documents: Array<{
    id: number;
    type: string;
    filename: string;
    path: string;
  }>;
}

// Tenant History Page Props
export interface TenantHistoryPageProps {
  pastTenants: PaginatedResponse<Tenant & {
    move_out_date?: string;
    total_payments?: number;
    lease?: Lease;
  }>;
  stats: {
    total: number;
    this_year: number;
  };
  buildings: Building[];
  filters: {
    search?: string;
    building_id?: number | string | null;
  };
}

// Tenant Ledger Transaction
export interface LedgerTransaction {
  id: number;
  type: 'invoice' | 'payment' | 'credit_note' | 'refund';
  date: string;
  description: string;
  debit?: number;
  credit?: number;
  balance: number;
  reference?: string;
}

// Tenant Ledger Summary
export interface LedgerSummary {
  opening_balance: number;
  total_charges: number;
  total_payments: number;
  total_credits: number;
  closing_balance: number;
}

// Tenant Ledger Page Props
export interface TenantLedgerPageProps {
  tenant: Tenant;
  activeLease: Lease | null;
  transactions: LedgerTransaction[];
  summary: LedgerSummary;
  filters: {
    date_from?: string;
    date_to?: string;
  };
}

// ===== MOVE-OUT PAGE TYPES =====

// Move-Out Deduction Category
export interface MoveOutDeductionCategory {
  id: number;
  name: string;
  description?: string;
  default_amount: number;
  always_apply: boolean;
}

// Move-Out Deduction
export interface MoveOutDeduction {
  id: number;
  move_out_id: number;
  category_id?: number;
  category?: MoveOutDeductionCategory;
  description: string;
  amount: number;
  notes?: string;
  photo_path?: string;
  auto_applied: boolean;
  created_at: string;
  updated_at: string;
}

// Move-Out
export interface MoveOut extends BaseEntity {
  lease_id: number;
  tenant_id: number;
  status: 'pending' | 'scheduled' | 'inspection' | 'completed' | 'cancelled';
  notice_date: string;
  intended_move_out_date: string;
  actual_move_out_date?: string;
  reason?: string;
  deposit_held: number;
  total_deductions: number;
  arrears_balance: number;
  refund_amount?: number;
  tenant?: Tenant;
  lease?: Lease & { unit?: Unit };
  inspection_notes?: string;
  inspection_photos?: string[];
  deductions?: MoveOutDeduction[];
}

// Move-Out Stats
export interface MoveOutStats {
  pending: number;
  scheduled: number;
  completed_this_month: number;
  total_refunded: number;
}

// Move-Outs Index Page Props
export interface MoveOutsIndexPageProps {
  moveOuts: PaginatedResponse<MoveOut>;
  status: string;
  stats: MoveOutStats;
}

// Move-Out Show Page Props
export interface MoveOutShowPageProps {
  moveOut: MoveOut;
  categories: MoveOutDeductionCategory[];
}

// Move-Out Create Page Props
export interface MoveOutCreatePageProps {
  lease: Lease & {
    tenant: Tenant;
    unit: Unit;
  };
}

// ===== BUILDING PAGE TYPES =====

// Building with extended properties for Index page
export interface BuildingListItem extends Building {
  building_type?: string;
  type_label?: string;
  address?: string;
  units_count: number;
  occupied_units_count: number;
  occupancy_rate: number;
  primary_photo?: string;
}

// Building Types lookup
export type BuildingTypesLookup = Record<string, string>;

// Amenity Options
export type AmenityOptionsLookup = Record<string, Record<string, string>>;

// Buildings Index Filters
export interface BuildingsIndexFilters {
  search?: string;
  type?: string;
  sort?: string;
}

// Buildings Index Page Props
export interface BuildingsIndexPageProps {
  buildings: BuildingListItem[];
  buildingTypes: BuildingTypesLookup;
  amenityOptions: AmenityOptionsLookup;
  filters: BuildingsIndexFilters;
}

// Building Edit Unit
export interface BuildingEditUnit extends Unit {
  floor_number: number;
  unit_type?: 'residential' | 'commercial';
}

// Building Edit Data
export interface BuildingEditData extends Building {
  total_floors: number;
  units_per_floor: number;
  building_type?: string;
  amenities?: {
    selected: string[];
    custom: string[];
  };
  coordinates?: {
    lat: number;
    lng: number;
  } | null;
  auto_generate_invoices?: boolean;
  invoice_generation_day?: number;
  auto_send_invoices?: boolean;
}

// Buildings Edit Page Props
export interface BuildingsEditPageProps {
  building: BuildingEditData;
  buildings: Building[];
  units: BuildingEditUnit[];
  amenityOptions: AmenityOptionsLookup;
}

// Invoices Show Page Props
export interface InvoicesShowPageProps {
  invoice: Invoice & {
    items?: InvoiceItem[];
    payments?: Payment[];
    lease?: Lease & {
      tenant?: Tenant;
      unit?: Unit & {
        building?: Building;
      };
    };
  };
}

// ===== FORM OPTIONS (dropdowns) =====

// Generic label/value option for dropdowns
export interface SelectOption {
  label: string;
  value: string;
}

// ===== PAYMENTS PAGE TYPES =====

// Payments BulkImport Page Props
export interface PaymentsBulkImportPageProps {
  buildings: Building[];
}

// Payments Record Page Props
export interface PaymentsRecordPageProps {
  paymentMethods: SelectOption[];
  buildings: Building[];
}

// ===== REFUNDS PAGE TYPES =====

// Refunds Create Page Props
export interface RefundsCreatePageProps {
  refundMethods: SelectOption[];
  refundReasons: SelectOption[];
}

// ===== INVITATIONS PAGE TYPES =====

// Caretaker Invitation (for Invitations/Index.vue)
export interface CaretakerInvitation {
  id: number;
  email: string;
  token: string;
  property: string;
  status: 'pending' | 'accepted' | 'expired';
  sent_at: string;
  accepted_at?: string;
}

// Caretaker Invitations Index Page Props
export interface CaretakerInvitationsIndexPageProps {
  invitations: CaretakerInvitation[];
  properties: Property[];
}

// Tenant Invitation (for TenantInvitations/Index.vue)
export interface TenantInvitation {
  id: number;
  email: string;
  tenant_name?: string;
  tenant_phone?: string;
  token: string;
  unit: string;
  building: string;
  property: string;
  rent_amount: number;
  service_charge?: number;
  deposit_amount: number;
  start_date?: string;
  start_date_display?: string;
  end_date?: string;
  notification_channels: string[];
  status: 'pending' | 'accepted' | 'expired';
  expires_at?: string;
  viewed_at?: string;
}

// Vacant Unit (for tenant invitation selection)
export interface VacantUnit {
  id: number;
  unit_number: string;
  building_name: string;
  property_name: string;
  target_rent: number;
}

// Tenant Invitations Index Page Props
export interface TenantInvitationsIndexPageProps {
  invitations: TenantInvitation[];
  vacantUnits: VacantUnit[];
  editInvitation?: TenantInvitation | null;
  smsConfigured: boolean;
  whatsappConfigured: boolean;
}

// Tenant Invitation Accept Page Props
export interface TenantInvitationAcceptPageProps {
  invitation: TenantInvitation;
  error?: string;
}
