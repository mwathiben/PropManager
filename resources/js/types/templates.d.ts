/**
 * Templates Domain Type Definitions
 * Interfaces for Invoice Templates, Receipt Templates, and Design Options
 */

import type { BaseEntity, Invoice, Payment, CreditNote, PaginatedResponse } from './finances';

// Template Design Type
export type TemplateDesign = 'classic' | 'modern' | 'minimal' | 'professional';

// Base Template Interface
export interface BaseTemplate extends BaseEntity {
  landlord_id: number;
  name: string;
  design: TemplateDesign;
  is_default: boolean;
  is_active: boolean;
  // Common display options
  show_logo: boolean;
  show_tax_number: boolean;
  show_footer: boolean;
  show_qr_code: boolean;
  // Colors
  primary_color: string;
  secondary_color: string;
  // Custom content
  custom_header?: string;
  custom_footer?: string;
}

// Invoice Template
export interface InvoiceTemplate extends BaseTemplate {
  // Invoice-specific options
  show_tenant_id: boolean;
  show_unit_details: boolean;
  show_lease_reference: boolean;
  show_due_date: boolean;
  show_late_warning: boolean;
  show_bank_details: boolean;
  show_payment_instructions: boolean;
  show_arrears_breakdown: boolean;
  show_water_details: boolean;
}

// Receipt Template
export interface ReceiptTemplate extends BaseTemplate {
  // Receipt-specific options
  show_tenant_id: boolean;
  show_unit_details: boolean;
  show_payment_method: boolean;
  show_reference_number: boolean;
  show_balance_after: boolean;
  show_thank_you_message: boolean;
  thank_you_message?: string;
}

// Credit Note Template
export interface CreditNoteTemplate extends BaseTemplate {
  show_reason: boolean;
  show_original_invoice: boolean;
  show_approval_info: boolean;
}

// Design Option (for design selector)
export interface DesignOption {
  id: TemplateDesign;
  name: string;
  description: string;
  preview_url?: string;
}

// Template Settings (landlord-level defaults)
export interface TemplateSettings {
  default_invoice_template_id?: number;
  default_receipt_template_id?: number;
  default_credit_note_template_id?: number;
  company_name?: string;
  company_address?: string;
  company_phone?: string;
  company_email?: string;
  tax_number?: string;
  bank_name?: string;
  bank_account_number?: string;
  bank_branch?: string;
  mpesa_paybill?: string;
  mpesa_account_hint?: string;
}

// Sample Invoice (for template preview)
export interface SampleInvoice {
  invoice_number: string;
  tenant_name: string;
  tenant_email: string;
  unit_number: string;
  building_name: string;
  due_date: string;
  period_start: string;
  period_end: string;
  items: Array<{
    description: string;
    quantity: number;
    unit_price: number;
    total: number;
  }>;
  subtotal: number;
  total_due: number;
  amount_paid: number;
  balance: number;
  previous_balance?: number;
}

// Sample Receipt (for template preview)
export interface SampleReceipt {
  receipt_number: string;
  tenant_name: string;
  unit_number: string;
  building_name: string;
  payment_date: string;
  payment_method: string;
  reference?: string;
  amount: number;
  invoice_number?: string;
  balance_after?: number;
}

// Invoice Template Edit Page Props
export interface InvoiceTemplateEditPageProps {
  template?: InvoiceTemplate;
  designOptions: DesignOption[];
  settings: TemplateSettings;
  sampleInvoice: SampleInvoice;
}

// Toggle Group (for receipt template editor)
export interface ToggleGroup {
  name: string;
  toggles: Array<{
    key: string;
    label: string;
    description?: string;
  }>;
}

// Receipt Template Edit Page Props
export interface ReceiptTemplateEditPageProps {
  template?: ReceiptTemplate;
  designOptions: DesignOption[];
  toggleGroups?: ToggleGroup[];
  settings: TemplateSettings;
  sampleReceipt: SampleReceipt;
}

// Invoice Settings Edit Page Props
export interface InvoiceSettingsEditPageProps {
  settings: TemplateSettings & {
    invoice_prefix?: string;
    invoice_starting_number?: number;
    auto_send_invoice?: boolean;
    payment_terms_days?: number;
    late_fee_enabled?: boolean;
    late_fee_percentage?: number;
    late_fee_grace_days?: number;
  };
}

// ===== CREDIT NOTES PAGE TYPES =====

// Credit Note Stats
export interface CreditNoteStats {
  total: number;
  pending: number;
  approved: number;
  applied: number;
  total_amount: number;
  pending_amount: number;
}

// Credit Note Reason Options
export type CreditNoteReasonOptions = Record<string, string>;

// Credit Notes Index Page Props
export interface CreditNotesIndexPageProps {
  creditNotes: PaginatedResponse<CreditNote>;
  stats: CreditNoteStats;
  filters: {
    search?: string;
    status?: string;
    date_from?: string;
    date_to?: string;
  };
  reasonOptions: CreditNoteReasonOptions;
}

// Credit Note Show Page Props
export interface CreditNoteShowPageProps {
  creditNote: CreditNote & {
    credit_number: string;
    tenant?: { id: number; name: string; email: string };
    lease?: { id: number; unit?: { unit_number: string; building?: { name: string } } };
    invoice?: Invoice;
    approved_by_user?: { name: string };
    created_by_user?: { name: string };
    applications?: Array<{
      id: number;
      invoice_id: number;
      amount: number;
      applied_at: string;
      invoice?: Invoice;
    }>;
  };
  reasonOptions: CreditNoteReasonOptions;
  outstandingInvoices: Invoice[];
}

// Credit Note Create Page Props
export interface CreditNoteCreatePageProps {
  reasonOptions: CreditNoteReasonOptions;
  tenantId?: string | number;
}
