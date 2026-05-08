/**
 * Tickets Domain Type Definitions
 * Interfaces for Ticket, TicketStatus, TicketPriority, and related entities
 */

import type { BaseEntity, Building, Unit, Tenant, PaginatedResponse } from './finances';

// Ticket Status (matches backend enum/constants)
export type TicketStatus = 'open' | 'in_progress' | 'resolved' | 'closed';

// Ticket Priority (matches backend enum/constants)
export type TicketPriority = 'low' | 'medium' | 'high' | 'urgent';

// Ticket Category (issue vs complaint)
export type TicketCategory = 'issue' | 'complaint';

// Ticket Subcategory
export interface TicketSubcategory {
  id: string;
  name: string;
  category: TicketCategory;
}

// Ticket Comment
export interface TicketComment extends BaseEntity {
  ticket_id: number;
  user_id: number;
  content: string;
  is_internal: boolean;
  user?: {
    name: string;
    profile_photo_url?: string | null;
  };
}

// Ticket Attachment
export interface TicketAttachment extends BaseEntity {
  ticket_id: number;
  filename: string;
  path: string;
  mime_type: string;
  size: number;
}

// Ticket
export interface Ticket extends BaseEntity {
  landlord_id: number;
  tenant_id?: number;
  unit_id?: number;
  building_id?: number;
  title: string;
  description: string;
  category: TicketCategory;
  subcategory?: string;
  status: TicketStatus;
  priority: TicketPriority;
  assigned_to?: number;
  resolved_at?: string;
  closed_at?: string;
  feedback_rating?: number;
  feedback_comment?: string;
  tenant?: Tenant;
  unit?: Unit;
  building?: Building;
  comments?: TicketComment[];
  attachments?: TicketAttachment[];
  assigned_user?: {
    name: string;
  };
}

// Ticket Stats
export interface TicketStats {
  total: number;
  open: number;
  in_progress: number;
  resolved: number;
  closed: number;
  urgent: number;
  high_priority: number;
  avg_resolution_time?: number;
}

// Ticket Filters
export interface TicketFilters {
  search?: string;
  status?: TicketStatus | '';
  priority?: TicketPriority | '';
  category?: TicketCategory | '';
  building_id?: number | string | null;
  wing_id?: number | string | null;
  assigned_to?: number | null;
}

// Status Option (for dropdowns)
export interface StatusOption {
  value: TicketStatus;
  label: string;
}

// Priority Option (for dropdowns)
export interface PriorityOption {
  value: TicketPriority;
  label: string;
}

// Category Option (for dropdowns)
export interface CategoryOption {
  value: TicketCategory;
  label: string;
}

// Tickets Index Page Props
export interface TicketsIndexPageProps {
  tickets: PaginatedResponse<Ticket>;
  buildings: Building[];
  stats: TicketStats;
  filters: TicketFilters;
  statuses: Record<TicketStatus, string>;
  priorities: Record<TicketPriority, string>;
  categories: Record<TicketCategory, string>;
}

// Caretaker (for assignment)
export interface Caretaker extends BaseEntity {
  name: string;
  email: string;
}

// Ticket Show Page Props
export interface TicketShowPageProps {
  ticket: Ticket;
  caretakers: Caretaker[];
  canAssign: boolean;
  canChangeStatus: boolean;
  canAddInternalComment: boolean;
  canSubmitFeedback: boolean;
  statuses: Record<TicketStatus, string>;
}

// Ticket Create Page Props
export interface TicketCreatePageProps {
  buildings: Building[];
  units: Unit[];
  defaultBuildingId?: number;
  defaultUnitId?: number;
  subcategories: Record<TicketCategory, Record<string, string>>;
  priorities: Record<TicketPriority, string>;
}

// Caretaker Tickets Page Props (different from main tickets page)
export interface CaretakerTicketsPageProps {
  tickets: PaginatedResponse<Ticket>;
  stats: TicketStats;
  filters: TicketFilters;
  statuses: Record<TicketStatus, string>;
  priorities: Record<TicketPriority, string>;
}

// Maintenance Hub Page Props
export interface MaintenanceHubPageProps {
  activeTab: string;
  tickets: PaginatedResponse<Ticket>;
  complaints: PaginatedResponse<Ticket>;
  filters: TicketFilters;
  stats: TicketStats;
  buildings: Building[];
  statusOptions: StatusOption[];
  priorityOptions: PriorityOption[];
  categoryOptions: CategoryOption[];
}

// Maintenance Tickets Tab Props
export interface MaintenanceTicketsTabProps {
  tickets: PaginatedResponse<Ticket>;
  buildings: Building[];
  stats: TicketStats;
  filters: TicketFilters;
  statuses: Record<TicketStatus, string>;
  priorities: Record<TicketPriority, string>;
}

// Maintenance Complaints Tab Props
export interface MaintenanceComplaintsTabProps {
  complaints: PaginatedResponse<Ticket>;
  buildings: Building[];
  stats: TicketStats;
  filters: TicketFilters;
  statuses: Record<TicketStatus, string>;
  priorities: Record<TicketPriority, string>;
}
