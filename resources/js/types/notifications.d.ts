/**
 * Notifications Domain Type Definitions
 * Interfaces for notification pages, preferences, and related entities
 */

import type { BaseEntity } from './finances';
import type { PaginatedResponse } from './global';

// Notification type (matches typeConfig in Tenant/Notifications.vue)
export type NotificationType =
  | 'rent_reminder'
  | 'arrears_notice'
  | 'invoice'
  | 'receipt'
  | 'rent_hike'
  | 'lease_expiry'
  | 'lease_renewal'
  | 'maintenance_notice'
  | 'general'
  | 'eviction_notice'
  | 'caretaker_invitation'
  | 'tenant_invitation';

// Notification channel type
export type NotificationChannel = 'email' | 'sms' | 'whatsapp' | 'push';

// Notification status
export type NotificationStatus = 'pending' | 'sent' | 'delivered' | 'read' | 'failed';

// Single tenant notification
export interface TenantNotification extends BaseEntity {
  type: NotificationType;
  subject: string;
  message: string;
  channel?: NotificationChannel;
  status?: NotificationStatus;
  read_at: string | null;
  // For invitation notifications
  is_invitation?: boolean;
  invitation_id?: number;
  invitation_type?: 'caretaker' | 'tenant';
}

// Tenant Notifications page props
export interface TenantNotificationsPageProps {
  notifications: PaginatedResponse<TenantNotification>;
  unreadCount: number;
  filter: string;
}

// ===== OPERATIONS/NOTIFICATIONS TAB TYPES =====

// Notification statistics (for Operations hub)
export interface NotificationStats {
  total_sent?: number;
  pending?: number;
  failed?: number;
  delivered?: number;
}

// Channel statistics (per-channel stats)
export interface ChannelStats {
  email?: { sent: number; failed: number };
  sms?: { sent: number; failed: number };
  whatsapp?: { sent: number; failed: number };
  push?: { sent: number; failed: number };
}

// Simple tenant reference for notification sending
export interface TenantReference {
  id: number;
  name: string;
  email: string;
}

// Notification template option
export interface NotificationTemplateOption {
  value: string;
  label: string;
}

// Scheduled notification
export interface ScheduledNotification extends BaseEntity {
  type: NotificationType;
  scheduled_at: string;
  recipient_count?: number;
  status: 'pending' | 'processing' | 'completed' | 'cancelled';
}

// Operations Notifications tab props
export interface OperationsNotificationsTabProps {
  stats: NotificationStats;
  recentNotifications: TenantNotification[];
  channelStats: ChannelStats;
  tenants: TenantReference[];
  templates: NotificationTemplateOption[];
  scheduled: ScheduledNotification[];
  setupComplete: boolean;
}

// ===== NOTIFICATIONS SETTINGS TAB TYPES =====

// Provider settings (flexible key-value for different providers)
export interface ProviderSettings {
  // Email settings
  mail_mailer?: string;
  mail_host?: string;
  mail_port?: string;
  mail_username?: string;
  mail_password?: string;
  mail_encryption?: string;
  mail_from_address?: string;
  mail_from_name?: string;
  // SMS settings
  sms_provider?: string;
  africastalking_username?: string;
  africastalking_api_key?: string;
  africastalking_sender_id?: string;
  twilio_sid?: string;
  twilio_token?: string;
  twilio_from?: string;
  // WhatsApp settings
  whatsapp_provider?: string;
  twilio_whatsapp_from?: string;
  // Push settings
  push_enabled?: boolean;
  vapid_public_key?: string;
  // Generic key-value support
  [key: string]: string | boolean | undefined;
}

// Global notification preferences (landlord-level)
export interface GlobalNotificationPreferences {
  // Quiet hours
  quiet_hours_enabled: boolean;
  quiet_hours_start: string;
  quiet_hours_end: string;
  quiet_hours_queue_notifications: boolean;
  // Retry settings
  notification_max_retries: number;
  notification_retry_delay: number;
  // Rate limiting
  notification_daily_limit_per_tenant: number;
  notification_hourly_limit_per_tenant: number;
  // Archive
  notification_archive_days: number;
  notification_track_read_status: boolean;
}

// WhatsApp template
export interface WhatsAppTemplate {
  type: string;
  sid?: string;
  label: string;
  name: string;
  content: string;
  variables: string[];
}

// Notifications Settings tab props (in Notifications Center)
export interface NotificationsSettingsTabProps {
  settings: ProviderSettings;
  globalPreferences: GlobalNotificationPreferences;
  setupComplete: boolean;
  whatsappTemplates: WhatsAppTemplate[];
}

// ===== NOTIFICATIONS INDEX PAGE TYPES =====

// Notification recipient reference
export interface NotificationRecipient {
  id?: number;
  name: string;
  email?: string;
}

// Detailed notification entry (for history/index display)
export interface NotificationEntry extends BaseEntity {
  type: NotificationType;
  subject: string;
  message?: string;
  channel: NotificationChannel;
  status: NotificationStatus;
  recipient?: NotificationRecipient;
  error_message?: string;
  delivered_at?: string;
}

// Building reference for filtering
export interface BuildingReference {
  id: number;
  name: string;
}

// History tab filters
export interface NotificationFilters {
  search?: string;
  status?: string;
  channel?: string;
  type?: string;
}

// Template placeholder object
export interface TemplatePlaceholders {
  [category: string]: string[];
}

// Notification template
export interface NotificationTemplate extends BaseEntity {
  type: NotificationType;
  name: string;
  subject: string;
  body: string;
  channel?: NotificationChannel;
  is_default?: boolean;
}

// Schedule type option
export interface ScheduleTypeOption {
  value: string;
  label: string;
}

// Notifications Index page props (main container)
export interface NotificationsIndexPageProps {
  activeTab: string;
  notifications: PaginatedResponse<NotificationEntry>;
  tenants: TenantReference[];
  buildings: BuildingReference[];
  filters: NotificationFilters;
  setupComplete: boolean;
  stats: NotificationStats;
  recentNotifications: NotificationEntry[];
  channelStats: ChannelStats;
  templates: NotificationTemplate[];
  notificationTypes: ScheduleTypeOption[];
  placeholders: TemplatePlaceholders;
  schedules: ScheduledNotification[];
  scheduleTypes: ScheduleTypeOption[];
  providers: ProviderSettings;
  smsProviders: ScheduleTypeOption[];
  currentSmsProvider: string;
  globalPreferences: GlobalNotificationPreferences;
}

// History Tab props
export interface NotificationsHistoryTabProps {
  notifications: PaginatedResponse<NotificationEntry>;
  filters: NotificationFilters;
}

// Overview Tab props
export interface NotificationsOverviewTabProps {
  stats: NotificationStats;
  recentNotifications: NotificationEntry[];
  channelStats: ChannelStats;
  tenants: TenantReference[];
  setupComplete: boolean;
}

// Setup Wizard props
export interface SetupWizardProps {
  show: boolean;
  settings: ProviderSettings;
}

// ===== NOTIFICATION TAB PARTIALS =====

// Templates Tab props (notification templates)
export interface NotificationsTemplatesTabProps {
  templates: NotificationTemplate[];
}

// Scheduled Tab props (scheduled notifications)
export interface NotificationsScheduledTabProps {
  schedules: ScheduledNotification[];
  templates: NotificationTemplate[];
}

// ===== VERIFICATION TYPES =====

// Verification template item
export interface VerificationTemplateItem {
  name: string;
  document_type: string;
  description: string;
  is_required: boolean;
}

// Verification template
export interface VerificationTemplate {
  id: number;
  name: string;
  property_id?: number | null;
  is_default: boolean;
  items: VerificationTemplateItem[];
  created_at?: string;
  updated_at?: string;
}

// Verification Templates page props
export interface VerificationTemplatesPageProps {
  templates: VerificationTemplate[];
  properties: Array<{ id: number; name: string }>;
}

// ===== CONSENT TYPES =====

// Consent document
export interface ConsentDocument {
  type: string;
  title: string;
  version: string;
  content: string;
  is_required: boolean;
}

// Consent Required page props
export interface ConsentRequiredPageProps {
  documents: ConsentDocument[];
}

// ===== PROFILE BUSINESS TAB TYPES =====

// User reference for BusinessProfileTab
export interface ProfileUser {
  id: number;
  name: string;
  email: string;
  mobile_number?: string;
}
