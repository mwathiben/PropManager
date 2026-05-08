/**
 * Profile Domain Type Definitions
 * Interfaces for user profile pages and components
 */

import type { LandlordProfile } from './settings';

// User role types
export type UserRole = 'landlord' | 'caretaker' | 'tenant' | 'admin';

// User for profile pages (authenticated user with profile fields)
export interface ProfileUser {
  id: number;
  name: string;
  email: string;
  role: UserRole;
  mobile_number?: string;
  profile_photo_path?: string;
  profile_photo_url?: string | null;
  email_verified_at?: string;
  two_factor_enabled?: boolean;
  created_at?: string;
  updated_at?: string;
}

// ===== PROFILE PAGE PROPS =====

// Profile Edit Page Props (main container)
export interface ProfileEditPageProps {
  user: ProfileUser;
  landlordProfile?: LandlordProfile | null;
  mustVerifyEmail?: boolean;
  status?: string;
}

// Personal Info Tab Props
export interface PersonalInfoTabProps {
  user: ProfileUser;
  mustVerifyEmail?: boolean;
  status?: string;
}

// Security Tab Props
export interface SecurityTabProps {
  user: ProfileUser;
}

// Business Profile Tab Props
export interface ProfileBusinessTabProps {
  landlordProfile?: LandlordProfile | null;
}

// Verification Tab Props
export interface ProfileVerificationTabProps {
  user: ProfileUser;
}

// Danger Zone Tab Props
export interface DangerZoneTabProps {
  user: ProfileUser;
}

// Notifications Tab Props (user preferences)
export interface ProfileNotificationsTabProps {
  user: ProfileUser;
  pushSubscription?: {
    endpoint: string;
    keys: {
      auth: string;
      p256dh: string;
    };
  } | null;
}
