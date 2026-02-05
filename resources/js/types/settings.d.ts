/**
 * Settings Domain Type Definitions
 * Interfaces for Settings pages, LandlordProfile, PaymentConfiguration, and related entities
 */

import type { BaseEntity } from './finances';

// Water billing types (matches PaymentConfiguration::WATER_BILLING_TYPES)
export type WaterBillingType = 'consumption' | 'flat_rate' | 'none';

// M-Pesa shortcode types (matches PaymentConfiguration::MPESA_SHORTCODE_TYPES)
export type MpesaShortcodeType = 'paybill' | 'till';

// OCR provider types
export type OcrProvider = 'none' | 'ocr_space' | 'google_vision' | 'azure_vision';

// Landlord Profile (matches App\Models\LandlordProfile)
export interface LandlordProfile extends BaseEntity {
  user_id: number;
  company_name?: string;
  business_registration_number?: string;
  profile_photo_path?: string;
  address?: string;
  city?: string;
  country?: string;
  tax_id?: string;
  website?: string;
  // Computed attributes
  profile_photo_url?: string | null;
  display_name?: string;
  full_address?: string | null;
}

// Payment Configuration (matches App\Models\PaymentConfiguration)
// Note: Actual secret keys are NEVER sent to frontend (PAY-V2-004)
// Only last4 masked versions are exposed for UI display
export interface PaymentConfiguration extends BaseEntity {
  landlord_id: number;
  default_rent?: number;
  water_billing_type: WaterBillingType;
  flat_water_rate?: number;
  water_unit_rate?: number;
  accepted_payment_methods: string[];
  bank_name?: string;
  bank_account_name?: string;
  bank_account_number?: string;
  bank_branch?: string;
  mpesa_paybill?: string;
  mpesa_account_name?: string;
  mpesa_shortcode_type?: MpesaShortcodeType;
  mpesa_shortcode?: string;
  mpesa_consumer_key_last4?: string;
  mpesa_consumer_secret_last4?: string;
  mpesa_b2c_shortcode?: string;
  mpesa_b2c_initiator?: string;
  mpesa_b2c_password_last4?: string;
  mpesa_b2c_security_credential_last4?: string;
  paystack_enabled: boolean;
  paystack_public_key?: string;
  paystack_secret_key_last4?: string;
  intasend_enabled: boolean;
  intasend_publishable_key?: string;
  intasend_secret_key_last4?: string;
  intasend_environment?: 'sandbox' | 'production';
}

// Payment method option for dropdown
export interface PaymentMethodOption {
  label: string;
  description?: string;
}

// Payment methods lookup (from PaymentConfiguration::getAvailablePaymentMethods)
export type PaymentMethodsLookup = Record<string, PaymentMethodOption>;

// OCR Settings (built in SettingsController::index)
export interface OcrSettings {
  provider: OcrProvider;
  enabled: boolean;
  auto_verify: boolean;
  has_api_key: boolean;
}

// OCR Provider info (from OcrService::getAvailableProviders)
export interface OcrProviderInfo {
  name: string;
  description: string;
  fields: string[];
}

// OCR providers lookup
export type OcrProvidersLookup = Record<string, OcrProviderInfo>;

// Branding Settings (built in SettingsController::index)
export interface BrandingSettings {
  invoice_number_format: string;
  invoice_footer_text: string;
  receipt_footer_text: string;
  business_logo_path: string;
  business_logo_url: string | null;
}

// Invoice number format options
export type InvoiceNumberFormats = Record<string, string>;

// Notification Defaults (subset of NotificationPreference for landlord defaults)
export interface NotificationDefaults extends BaseEntity {
  user_id: number;
  landlord_id: number;
  // Channel preferences
  email_enabled: boolean;
  sms_enabled: boolean;
  whatsapp_enabled: boolean;
  push_enabled: boolean;
  in_app_enabled: boolean;
  // Notification type preferences
  rent_reminder_enabled: boolean;
  arrears_notice_enabled: boolean;
  invoice_enabled: boolean;
  receipt_enabled: boolean;
  rent_hike_enabled: boolean;
  lease_expiry_enabled: boolean;
  lease_renewal_enabled: boolean;
  maintenance_notice_enabled: boolean;
  general_enabled: boolean;
  eviction_notice_enabled: boolean;
  caretaker_invitation_enabled: boolean;
  tenant_invitation_enabled: boolean;
  // Other settings
  rent_reminder_days_before?: number;
  preferred_time?: string;
  whatsapp_number?: string;
  // Quiet hours
  quiet_hours_enabled: boolean;
  quiet_hours_start?: string;
  quiet_hours_end?: string;
}

// Settings Page Props (from SettingsController::index)
export interface SettingsPageProps {
  activeTab: string;
  landlordProfile: LandlordProfile | null;
  paymentConfig: PaymentConfiguration;
  paymentMethods: PaymentMethodsLookup;
  ocrSettings: OcrSettings;
  ocrProviders: OcrProvidersLookup;
  brandingSettings: BrandingSettings;
  notificationDefaults: NotificationDefaults | null;
  twoFactorEnabled: boolean;
  invoiceNumberFormats: InvoiceNumberFormats;
}

// Tab-specific props for Settings partials

// PaymentMethodsTab props
export interface PaymentMethodsTabProps {
  paymentConfig: PaymentConfiguration;
  paymentMethods: PaymentMethodsLookup;
}

// IntegrationsTab props
export interface IntegrationsTabProps {
  ocrSettings: OcrSettings;
  ocrProviders: OcrProvidersLookup;
}

// BrandingTab props
export interface BrandingTabProps {
  brandingSettings: BrandingSettings;
  invoiceNumberFormats: InvoiceNumberFormats;
}

// NotificationsTab props (Settings page, not Profile)
export interface SettingsNotificationsTabProps {
  notificationDefaults: NotificationDefaults | null;
}

// ===== SUBSCRIPTION TYPES =====

// Subscription Status
export type SubscriptionStatus = 'active' | 'trialing' | 'cancelled' | 'past_due' | 'paused';

// Subscription Plan
export interface SubscriptionPlan extends BaseEntity {
  name: string;
  slug: string;
  description?: string;
  price_monthly: number;
  price_yearly: number;
  yearly_savings?: number;
  features: string[];
  is_free: boolean;
  is_active: boolean;
}

// Subscription
export interface Subscription extends BaseEntity {
  user_id: number;
  plan_id: number;
  plan?: SubscriptionPlan;
  status: SubscriptionStatus;
  status_label?: string;
  billing_cycle: 'monthly' | 'yearly';
  current_period_start?: string;
  current_period_end?: string;
  trial_ends_at?: string;
  cancelled_at?: string;
  ends_at?: string;
  ended?: boolean;
}

// Subscription Payment
export interface SubscriptionPayment extends BaseEntity {
  subscription_id: number;
  subscription?: Subscription;
  amount: number;
  currency: string;
  status: 'pending' | 'successful' | 'failed';
  status_label?: string;
  paid_at?: string;
  reference?: string;
}

// Usage Limits
export interface UsageData {
  current: number;
  limit: number;
}

// Subscription Index Page Props
export interface SubscriptionIndexPageProps {
  subscription: Subscription | null;
  currentPlan: SubscriptionPlan | null;
  plans: SubscriptionPlan[];
  payments: SubscriptionPayment[];
  usage: Record<string, UsageData>;
  paystackPublicKey: string;
  paystackConfigured: boolean;
}

// Subscription Plans Page Props
export interface SubscriptionPlansPageProps {
  plans: SubscriptionPlan[];
  currentPlan: SubscriptionPlan | null;
  billingCycle: string;
}

// ===== PAYOUT ACCOUNTS TYPES =====

// Payout Account
export interface PayoutAccount {
  id: number;
  bank_name: string;
  account_name: string;
  account_number: string;
  bank_code?: string;
  is_primary: boolean;
  is_verified: boolean;
  verification_status: 'pending' | 'verified' | 'failed';
  created_at?: string;
}

// Payout Accounts Page Props
export interface PayoutAccountsPageProps {
  accounts: PayoutAccount[];
  hasPrimaryAccount: boolean;
  hasVerifiedAccount: boolean;
  currentFeePercentage: number;
  billingModel: string;
}

// ===== TWO-FACTOR RECOVERY CODES TYPES =====

// Two Factor Recovery Codes Page Props
export interface TwoFactorRecoveryCodesPageProps {
  recoveryCodes: string[];
}
