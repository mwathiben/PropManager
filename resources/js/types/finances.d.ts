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

// Invoice Status
export type InvoiceStatus = 'draft' | 'sent' | 'viewed' | 'partial' | 'paid' | 'overdue' | 'void';

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
