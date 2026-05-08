/**
 * Operations Domain Type Definitions
 * Interfaces for imports, inbox, team management, and bulk operations
 */

import type { BaseEntity, Building, Property, Tenant, PaginatedResponse } from './finances';

// ===== IMPORTS =====

// Import Status
export type ImportStatus = 'pending' | 'processing' | 'completed' | 'failed' | 'cancelled';

// Import Type
export type ImportType = 'tenants' | 'units' | 'payments' | 'water_readings';

// Import Record
export interface Import extends BaseEntity {
  landlord_id: number;
  type: ImportType;
  filename: string;
  original_filename: string;
  status: ImportStatus;
  total_rows: number;
  processed_rows: number;
  successful_rows: number;
  failed_rows: number;
  error_log?: string;
  started_at?: string;
  completed_at?: string;
  uploaded_by?: number;
  uploader?: {
    name: string;
  };
}

// Import Template
export interface ImportTemplate {
  id: string;
  name: string;
  type: ImportType;
  description: string;
  columns: string[];
  sample_url?: string;
}

// ===== INBOX =====

// Inbox Message Type
export type InboxMessageType = 'inquiry' | 'support' | 'feedback' | 'notification';

// Inbox Message Status
export type InboxMessageStatus = 'unread' | 'read' | 'replied' | 'archived';

// Inbox Message
export interface InboxMessage extends BaseEntity {
  landlord_id: number;
  from_user_id?: number;
  from_email?: string;
  from_name?: string;
  subject: string;
  body: string;
  type: InboxMessageType;
  status: InboxMessageStatus;
  read_at?: string;
  replied_at?: string;
  archived_at?: string;
  from_user?: {
    name: string;
    email: string;
    profile_photo_url?: string | null;
  };
}

// ===== TEAM =====

// Invitation Status
export type InvitationStatus = 'pending' | 'accepted' | 'expired' | 'cancelled';

// Team Invitation
export interface TeamInvitation extends BaseEntity {
  landlord_id: number;
  property_id?: number;
  email: string;
  role: 'caretaker';
  token: string;
  status: InvitationStatus;
  accepted_at?: string;
  expires_at: string;
  property?: Property;
}

// Caretaker (team member)
export interface Caretaker extends BaseEntity {
  name: string;
  email: string;
  mobile_number?: string;
  profile_photo_url?: string | null;
  landlord_id: number;
  assigned_property_id?: number;
  assigned_property?: Property;
  assigned_buildings?: Building[];
  is_active: boolean;
  last_login_at?: string;
}

// ===== BULK OPERATIONS =====

// Bulk Operation Type
export type BulkOperationType =
  | 'rent_adjustment'
  | 'unit_status'
  | 'lease_termination'
  | 'lease_extension'
  | 'deposit_adjustment'
  | 'target_rent'
  | 'meter_numbers';

// Bulk Operation Status
export type BulkOperationStatus = 'pending' | 'processing' | 'completed' | 'failed';

// Bulk Operation
export interface BulkOperation extends BaseEntity {
  landlord_id: number;
  type: BulkOperationType;
  status: BulkOperationStatus;
  total_items: number;
  processed_items: number;
  successful_items: number;
  failed_items: number;
  parameters?: Record<string, unknown>;
  error_log?: string;
  started_at?: string;
  completed_at?: string;
}

// Bulk Stats
export interface BulkStats {
  recent_operations: number;
  pending_operations: number;
  total_affected_units: number;
}

// Building with unit counts (for bulk operations)
export interface BuildingWithCounts extends Building {
  units_count: number;
  occupied_units_count: number;
  vacant_units_count: number;
}

// ===== NOTIFICATIONS TAB =====

// Notification Stats
export interface NotificationStats {
  sent_today: number;
  sent_this_week: number;
  sent_this_month: number;
  pending_scheduled: number;
  failed_count: number;
}

// Channel Stats
export interface ChannelStats {
  email: { sent: number; failed: number; pending: number };
  sms: { sent: number; failed: number; pending: number };
  push: { sent: number; failed: number; pending: number };
  whatsapp: { sent: number; failed: number; pending: number };
}

// Recent Notification
export interface RecentNotification extends BaseEntity {
  recipient_id: number;
  recipient_type: string;
  channel: 'email' | 'sms' | 'push' | 'whatsapp';
  subject?: string;
  status: 'sent' | 'failed' | 'pending';
  sent_at?: string;
  recipient?: {
    name: string;
    email?: string;
  };
}

// Notification Template
export interface NotificationTemplate extends BaseEntity {
  landlord_id: number;
  name: string;
  type: string;
  subject?: string;
  body: string;
  channels: string[];
  is_active: boolean;
}

// Scheduled Notification
export interface ScheduledNotification extends BaseEntity {
  landlord_id: number;
  template_id?: number;
  recipient_filter?: Record<string, unknown>;
  scheduled_for: string;
  status: 'pending' | 'sent' | 'cancelled';
  template?: NotificationTemplate;
}

// ===== PAGE PROPS =====

// Operations Hub Page Props
export interface OperationsHubPageProps {
  activeTab: string;
  // Notifications tab
  stats: NotificationStats;
  recentNotifications: RecentNotification[];
  channelStats: ChannelStats;
  tenants: Tenant[];
  templates: NotificationTemplate[];
  scheduled: ScheduledNotification[];
  setupComplete: boolean;
  // Bulk tab
  buildingsWithCounts: BuildingWithCounts[];
  activeTenantCount: number;
  bulkOperations: BulkOperation[];
  // Team tab
  invitations: PaginatedResponse<TeamInvitation>;
  caretakers: Caretaker[];
  // Imports tab
  imports: PaginatedResponse<Import>;
  importTemplates: ImportTemplate[];
  // Inbox tab
  inbox: PaginatedResponse<InboxMessage>;
  inboxUnreadCount: number;
  // Common
  buildings: Building[];
}

// Operations Team Tab Props
export interface OperationsTeamTabProps {
  invitations: PaginatedResponse<TeamInvitation>;
  caretakers: Caretaker[];
  buildings: Building[];
}

// Operations Imports Tab Props
export interface OperationsImportsTabProps {
  imports: PaginatedResponse<Import>;
  importTemplates: ImportTemplate[];
}

// Operations Inbox Tab Props
export interface OperationsInboxTabProps {
  inbox: PaginatedResponse<InboxMessage>;
  inboxUnreadCount: number;
}

// Operations Bulk Tab Props
export interface OperationsBulkTabProps {
  buildingsWithCounts?: BuildingWithCounts[];
  activeTenantCount?: number;
  bulkOperations?: BulkOperation[];
  bulkStats?: BulkStats;
  buildings: Building[];
}

// Settings Privacy Page Props
export interface PrivacySettingsPageProps {
  deletionStatus: {
    requested: boolean;
    requested_at?: string;
    scheduled_at?: string;
  } | null;
  canDelete: {
    allowed: boolean;
    reason?: string;
    blockers?: string[];
  };
  gracePeriodDays: number;
}

// Reports Index Page Props
export interface ReportsIndexPageProps {
  analytics: {
    period: string;
    revenue: {
      current: number;
      previous: number;
      trend: number;
    };
    occupancy: {
      rate: number;
      occupied: number;
      total: number;
    };
    arrears: {
      total: number;
      count: number;
      aging: Record<string, number>;
    };
    collections: {
      total: number;
      by_method: Record<string, number>;
    };
    expenses?: {
      total: number;
      by_category?: Record<string, number>;
    };
  };
  availablePeriods: Record<string, string>;
}

// Imports Index Page Props
export interface ImportsIndexPageProps {
  imports: PaginatedResponse<Import>;
  importTypes: Record<ImportType, string>;
  buildings: Building[];
  filters: {
    building_id?: number | string | null;
    wing_id?: number | string | null;
    type?: ImportType | '';
    status?: ImportStatus | '';
  };
}

// Imports Show Page Props
export interface ImportsShowPageProps {
  importRecord: Import & {
    errors?: Array<{
      row: number;
      field: string;
      message: string;
    }>;
  };
}

// Inbox Index Page Props
export interface InboxIndexPageProps {
  messages: PaginatedResponse<InboxMessage>;
  unreadCount: number;
  filters: {
    search?: string;
    status?: InboxMessageStatus | '';
  };
}

// Inbox Show Page Props
export interface InboxShowPageProps {
  message: InboxMessage & {
    replies?: Array<{
      id: number;
      body: string;
      sent_at: string;
      sent_by?: {
        name: string;
      };
    }>;
  };
}
