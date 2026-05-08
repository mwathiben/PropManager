/**
 * Shared Components Type Definitions
 * Interfaces for reusable Vue component props that use Object/Array types
 */

import type { Component } from 'vue';
import type { Payment, Invoice, Lease, Building } from './finances';

// ===== BREADCRUMB COMPONENT =====

export interface BreadcrumbItem {
  label: string;
  href?: string;
}

export interface BreadcrumbProps {
  items?: BreadcrumbItem[];
}

// ===== ACTION ITEM CARD COMPONENT =====

export type ActionItemUrgency = 'critical' | 'high' | 'medium' | 'low';

export interface ActionItemCardProps {
  urgency?: ActionItemUrgency;
  icon: Component;
  title: string;
  count?: number;
  description?: string;
  actionLabel?: string;
  actionHref?: string;
}

// ===== BUILDING WING FILTER COMPONENT =====

export interface Wing {
  id: number;
  name: string;
}

export interface BuildingWithWings extends Building {
  wings?: Wing[];
}

export interface BuildingWingFilterProps {
  buildings?: BuildingWithWings[];
  buildingId?: string | number | null;
  wingId?: string | number | null;
  buildingPlaceholder?: string;
  wingPlaceholder?: string;
  showBadge?: boolean;
}

// ===== INVITATION BANNER COMPONENT =====

export interface PendingInvitation {
  id: number;
  type: 'caretaker' | 'tenant';
  landlord_name: string;
  property_name: string;
  unit_number?: string;
  rent_amount?: number;
  expires_at: string;
}

export interface InvitationBannerProps {
  invitations?: PendingInvitation[];
}

// ===== UNIT FILTERS COMPONENT =====

export interface UnitFiltersProps {
  floor?: string | number;
  unitType?: string;
  status?: string;
  availableFloors?: number[];
  availableUnitTypes?: string[];
}

// ===== TENANT PROFILE DOCUMENTS TAB =====

export interface TenantDocument {
  id: number;
  title?: string;
  file_name: string;
  document_type: string;
  mime_type?: string;
  file_size_formatted?: string;
  description?: string;
  documentable_type?: string;
  created_at: string;
}

export interface TenantDocumentsTabProps {
  documents?: TenantDocument[];
}

// ===== TENANT PROFILE HISTORY TAB =====

export interface PaymentWithInvoice extends Payment {
  invoice?: {
    invoice_number: string;
  };
}

export interface InvoiceForHistory {
  id: number;
  invoice_number: string;
  total_amount: number;
  due_date: string;
  status: string;
}

export interface LeaseForHistory extends Lease {
  unit?: {
    unit_number: string;
    building?: {
      name: string;
    };
  };
}

export interface TenantHistoryTabProps {
  payments?: PaymentWithInvoice[];
  invoices?: InvoiceForHistory[];
  pastLeases?: LeaseForHistory[];
}

// ===== TENANT PROFILE NOTES CONTACTS TAB =====

export interface TenantNoteWithAuthor {
  id: number;
  content: string;
  is_pinned?: boolean;
  created_at: string;
  author?: {
    name: string;
  };
}

export interface EmergencyContactExtended {
  id: number;
  name: string;
  phone: string;
  email?: string;
  relationship: string;
  is_primary?: boolean;
}

export interface TenantNotesContactsTabProps {
  tenantNotes?: TenantNoteWithAuthor[];
  emergencyContacts?: EmergencyContactExtended[];
}

// ===== TICKET ACTIVITY TIMELINE COMPONENT =====

export type TicketActivityAction =
  | 'created'
  | 'status_changed'
  | 'assigned'
  | 'commented'
  | 'resolved'
  | 'closed'
  | 'feedback_submitted';

export interface TicketActivity {
  id: number;
  action: TicketActivityAction;
  description?: string;
  old_value?: string;
  new_value?: string;
  created_at: string;
  user?: {
    name: string;
  };
}

export interface TicketActivityTimelineProps {
  activities: TicketActivity[];
}

// ===== MODAL COMPONENTS =====

// Building for wing modal
export interface BuildingForWingModal {
  id: number;
  name: string;
  is_wing?: boolean;
  parent_building_id?: number | null;
  unit_prefix?: string;
}

// Add Wing Modal props
export interface AddWingModalProps {
  show: boolean;
  propertyId?: number | string;
  buildings?: BuildingForWingModal[];
  defaultBuildingId?: number | string | null;
}

// Tenant for notification modals
export interface TenantForNotification {
  id: number;
  name: string;
  email: string;
  phone?: string;
}

// Notification type option
export interface NotificationType {
  value: string;
  label: string;
}

// Notification channel option
export interface NotificationChannel {
  value: string;
  label: string;
}

// Bulk Send Notification Modal props
export interface BulkSendNotificationModalProps {
  show: boolean;
  tenants?: TenantForNotification[];
  notificationTypes?: NotificationType[];
  channels?: NotificationChannel[];
}

// Eviction Notice Modal props
export interface EvictionNoticeModalProps {
  show: boolean;
  tenants?: TenantForNotification[];
  channels?: NotificationChannel[];
}

// Mass Hike Modal props
export interface MassHikeModalProps {
  show: boolean;
  buildingName?: string;
  occupiedUnits?: number;
  unitIds?: number[];
}

// Send Notification Modal props
export interface SendNotificationModalProps {
  show: boolean;
  tenants?: TenantForNotification[];
  notificationTypes?: NotificationType[];
}

// Upload Document Modal props (no Object/Array props - only Boolean)
export interface UploadDocumentModalProps {
  show: boolean;
}
