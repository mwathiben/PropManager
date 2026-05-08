/**
 * Archive Domain Type Definitions
 * Interfaces for archived documents, leases, and activity logs
 */

import type { BaseEntity, Building, Lease, Unit, PaginatedResponse } from './finances';

// Document Type (matches backend enum/constants)
export type DocumentType =
  | 'lease_agreement'
  | 'tenant_id'
  | 'tenant_passport'
  | 'bank_statement'
  | 'payslip'
  | 'reference_letter'
  | 'utility_bill'
  | 'receipt'
  | 'invoice'
  | 'other';

// Document
export interface Document extends BaseEntity {
  landlord_id: number;
  documentable_type: string;
  documentable_id: number;
  type: DocumentType;
  filename: string;
  original_filename: string;
  path: string;
  mime_type: string;
  size: number;
  uploaded_by?: number;
  description?: string;
  metadata?: Record<string, unknown>;
}

// Archived Lease (terminated/expired lease)
export interface ArchivedLease extends Lease {
  terminated_at?: string;
  termination_reason?: string;
  move_out_date?: string;
  deposit_refunded?: number;
  deposit_forfeited?: number;
  final_balance?: number;
}

// Activity Log Action Type
export type ActivityLogAction =
  | 'created'
  | 'updated'
  | 'deleted'
  | 'status_changed'
  | 'payment_received'
  | 'invoice_sent'
  | 'lease_started'
  | 'lease_terminated'
  | 'login'
  | 'logout'
  | 'settings_updated';

// Activity Log
export interface ActivityLog extends BaseEntity {
  landlord_id: number;
  user_id?: number;
  log_name: string;
  description: string;
  subject_type?: string;
  subject_id?: number;
  causer_type?: string;
  causer_id?: number;
  event?: ActivityLogAction;
  properties?: Record<string, unknown>;
  batch_uuid?: string;
  user?: {
    name: string;
    email: string;
    profile_photo_url?: string | null;
  };
}

// Activity Stats
export interface ActivityStats {
  total: number;
  today: number;
  this_week: number;
  this_month: number;
  by_type?: Record<string, number>;
}

// Archive Filters
export interface ArchiveFilters {
  search?: string;
  type?: DocumentType | '';
  building_id?: number | string | null;
  date_from?: string;
  date_to?: string;
}

// Activity Filters
export interface ActivityFilters {
  search?: string;
  event?: ActivityLogAction | '';
  user_id?: number | null;
  date_from?: string;
  date_to?: string;
}

// Archive Hub Page Props
export interface ArchiveHubPageProps {
  activeTab: string;
  documents: PaginatedResponse<Document>;
  leases: PaginatedResponse<ArchivedLease>;
  activityLogs: PaginatedResponse<ActivityLog>;
  filters: ArchiveFilters;
  buildings: Building[];
  documentTypes: Array<{ value: DocumentType; label: string }>;
}

// Archive Documents Tab Props
export interface ArchiveDocumentsTabProps {
  documents: PaginatedResponse<Document>;
  filters: ArchiveFilters;
  buildingsWithWings?: Building[];
  documentTypes: Array<{ value: DocumentType; label: string }>;
}

// Archive Leases Tab Props
export interface ArchiveLeasesTabProps {
  leases: PaginatedResponse<ArchivedLease>;
  buildings: Building[];
  filters: ArchiveFilters;
}

// Archive Activity Tab Props
export interface ArchiveActivityTabProps {
  activities: PaginatedResponse<ActivityLog>;
  activityTypes: Array<{ value: string; label: string }>;
  stats: ActivityStats;
  filters: ActivityFilters;
}

// Documents Index Page Props
export interface DocumentsIndexPageProps {
  documents: PaginatedResponse<Document>;
  buildings: Building[];
  filters: ArchiveFilters;
}

// Activity Logs Index Page Props
export interface ActivityLogsIndexPageProps {
  activities: PaginatedResponse<ActivityLog>;
  activityTypes: Array<{ value: string; label: string }>;
  stats: ActivityStats;
  filters: ActivityFilters;
}
