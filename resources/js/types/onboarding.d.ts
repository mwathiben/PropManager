/**
 * Onboarding Domain Type Definitions
 * Interfaces for Onboarding wizard pages and related entities
 */

import type { BaseEntity } from './finances';
import type { PaymentConfiguration, WaterBillingType } from './settings';

// Property type (matches Laravel enum or validation)
export type PropertyType = 'residential' | 'estate' | 'commercial' | 'mixed';

// User info for onboarding
export interface OnboardingUser extends BaseEntity {
  name: string;
  email: string;
  mobile_number?: string;
}

// Landlord profile for onboarding (subset of LandlordProfile)
export interface OnboardingProfile {
  company_name?: string;
  business_registration_number?: string;
  address?: string;
  city?: string;
  country?: string;
}

// Property for onboarding
export interface OnboardingProperty extends BaseEntity {
  name: string;
  type: PropertyType;
  address?: string;
}

// Payment config for onboarding (subset used in wizard)
export interface OnboardingPaymentConfig {
  default_rent?: number;
  water_billing_type?: WaterBillingType;
  flat_water_rate?: number;
  water_unit_rate?: number;
  accepted_payment_methods?: string[];
  default_currency?: string;
  bank_name?: string;
  bank_account_name?: string;
  bank_account_number?: string;
  mpesa_shortcode?: string;
  mpesa_account_name?: string;
}

// Existing invitation for team step
export interface OnboardingInvitation {
  id: number;
  email: string;
  status: 'pending' | 'accepted' | 'expired';
  created_at?: string;
}

// Vacant unit for first tenant step
export interface OnboardingVacantUnit {
  id: number;
  unit_number: string;
  building_name: string;
  property_name: string;
  target_rent: number;
}

// Onboarding summary (final step)
export interface OnboardingSummary {
  properties: number;
  buildings: number;
  units: number;
  hasPaymentConfig: boolean;
}

// Step data (generic object for step-specific data)
export type OnboardingStepData = Record<string, unknown>;

// Onboarding page props (from OnboardingController)
export interface OnboardingPageProps {
  currentStep: number;
  totalSteps: number;
  completedSteps: number[];
  stepData: OnboardingStepData;
  stepName: string;
  isOptionalStep: boolean;
  // Step-specific props (may be undefined depending on step)
  profile?: OnboardingProfile;
  user?: OnboardingUser;
  existingProperty?: OnboardingProperty;
  property?: OnboardingProperty;
  paymentConfig?: OnboardingPaymentConfig;
  existingInvitations?: OnboardingInvitation[];
  vacantUnits?: OnboardingVacantUnit[];
  summary?: OnboardingSummary;
}
