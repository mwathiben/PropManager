/**
 * Type exports barrel file
 * Import all types from here: import type { Invoice, Payment } from '@/types';
 */

// Core entities (invoices, payments, leases, etc.)
export * from './finances';

// Settings domain (landlord profile, payment config, OCR, branding)
export * from './settings';

// Dashboard domain (admin dashboard, caretaker dashboard)
export * from './dashboard';

// Notifications domain (tenant notifications, provider settings)
export * from './notifications';

// Onboarding domain (wizard steps, property setup)
export * from './onboarding';

// Shared component props (map, modals, cards)
export * from './components';

// Tickets domain (tickets, comments, priorities)
export * from './tickets';

// Water domain (readings, settings, billing)
export * from './water';

// Archive domain (documents, archived leases, activity logs)
export * from './archive';

// Operations domain (imports, inbox, team, bulk operations)
export * from './operations';

// Help domain (FAQs, articles, categories)
export * from './help';

// Templates domain (invoice/receipt templates, design options)
export * from './templates';

// Tenants domain (invitations, verifications, move-outs)
export * from './tenants';

// Profile domain (user profile pages)
export * from './profile';

// Tenant portal domain (tenant-facing pages)
export * from './tenant-portal';

// Shared components (breadcrumb, filters, timeline, etc.)
export * from './shared-components';

// Bulk operations domain (rent adjustment, lease management, etc.)
export * from './bulk-operations';
