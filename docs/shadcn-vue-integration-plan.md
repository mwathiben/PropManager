# shadcn-vue Integration Plan for PropManager

## Decisions
- **Approach**: Gradual migration (add alongside existing, migrate page-by-page)
- **Dark mode**: Yes - configure light/dark theming
- **Priority**: All categories (Forms, Modals, Data Display)

---

# PHASE 1: Installation & Dependencies

## Step 1.1: Install Core Dependencies
```bash
npm install -D tailwindcss-animate class-variance-authority clsx tailwind-merge
npm install radix-vue
npm install lucide-vue-next
```

## Step 1.2: Initialize shadcn-vue
```bash
npx shadcn-vue@latest init
```
Select these options:
- Style: **Default**
- Base color: **Slate**
- CSS variables: **Yes**
- Components path: `resources/js/Components/ui`
- Utilities path: `resources/js/lib`
- TypeScript: **No**

## Step 1.3: Install All Required Components
```bash
# Form components
npx shadcn-vue@latest add button input label checkbox select textarea switch radio-group

# Modal/overlay components
npx shadcn-vue@latest add dialog sheet dropdown-menu popover tooltip hover-card

# Data display components
npx shadcn-vue@latest add card badge avatar table pagination tabs

# Feedback components
npx shadcn-vue@latest add alert toast separator sonner skeleton

# Navigation & utility
npx shadcn-vue@latest add breadcrumb command scroll-area collapsible accordion

# Date/time (for filters)
npx shadcn-vue@latest add calendar
```

**Total: ~30 shadcn components**

---

# PHASE 2: Configuration Files

## Step 2.1: tailwind.config.js

Replace entire file:

```javascript
import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';
import animate from 'tailwindcss-animate';

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: 'class',

    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
        './resources/js/**/*.js',
    ],

    theme: {
        container: {
            center: true,
            padding: '2rem',
            screens: {
                '2xl': '1400px',
            },
        },
        extend: {
            colors: {
                border: 'hsl(var(--border))',
                input: 'hsl(var(--input))',
                ring: 'hsl(var(--ring))',
                background: 'hsl(var(--background))',
                foreground: 'hsl(var(--foreground))',
                primary: {
                    DEFAULT: 'hsl(var(--primary))',
                    foreground: 'hsl(var(--primary-foreground))',
                },
                secondary: {
                    DEFAULT: 'hsl(var(--secondary))',
                    foreground: 'hsl(var(--secondary-foreground))',
                },
                destructive: {
                    DEFAULT: 'hsl(var(--destructive))',
                    foreground: 'hsl(var(--destructive-foreground))',
                },
                muted: {
                    DEFAULT: 'hsl(var(--muted))',
                    foreground: 'hsl(var(--muted-foreground))',
                },
                accent: {
                    DEFAULT: 'hsl(var(--accent))',
                    foreground: 'hsl(var(--accent-foreground))',
                },
                popover: {
                    DEFAULT: 'hsl(var(--popover))',
                    foreground: 'hsl(var(--popover-foreground))',
                },
                card: {
                    DEFAULT: 'hsl(var(--card))',
                    foreground: 'hsl(var(--card-foreground))',
                },
            },
            borderRadius: {
                lg: 'var(--radius)',
                md: 'calc(var(--radius) - 2px)',
                sm: 'calc(var(--radius) - 4px)',
            },
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
            keyframes: {
                'accordion-down': {
                    from: { height: '0' },
                    to: { height: 'var(--radix-accordion-content-height)' },
                },
                'accordion-up': {
                    from: { height: 'var(--radix-accordion-content-height)' },
                    to: { height: '0' },
                },
            },
            animation: {
                'accordion-down': 'accordion-down 0.2s ease-out',
                'accordion-up': 'accordion-up 0.2s ease-out',
            },
        },
    },

    plugins: [forms, animate],
};
```

## Step 2.2: vite.config.js

Add path alias:

```javascript
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import path from 'path';

export default defineConfig({
    plugins: [
        laravel({
            input: 'resources/js/app.js',
            refresh: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
    ],
    resolve: {
        alias: {
            '@': path.resolve(__dirname, './resources/js'),
        },
    },
});
```

## Step 2.3: jsconfig.json

Update paths:

```json
{
    "compilerOptions": {
        "baseUrl": ".",
        "paths": {
            "@/*": ["resources/js/*"],
            "ziggy-js": ["./vendor/tightenco/ziggy"]
        },
        "target": "ES2020",
        "module": "ESNext",
        "moduleResolution": "bundler",
        "strict": false,
        "jsx": "preserve",
        "resolveJsonModule": true,
        "isolatedModules": true,
        "esModuleInterop": true,
        "lib": ["ES2020", "DOM", "DOM.Iterable"],
        "skipLibCheck": true,
        "noEmit": true
    },
    "include": ["resources/js/**/*"],
    "exclude": ["node_modules", "public"]
}
```

## Step 2.4: resources/css/app.css

Replace with theme variables:

```css
@tailwind base;
@tailwind components;
@tailwind utilities;

@layer base {
    :root {
        --background: 0 0% 100%;
        --foreground: 222.2 84% 4.9%;
        --card: 0 0% 100%;
        --card-foreground: 222.2 84% 4.9%;
        --popover: 0 0% 100%;
        --popover-foreground: 222.2 84% 4.9%;
        --primary: 238.7 83.5% 66.7%;
        --primary-foreground: 210 40% 98%;
        --secondary: 210 40% 96.1%;
        --secondary-foreground: 222.2 47.4% 11.2%;
        --muted: 210 40% 96.1%;
        --muted-foreground: 215.4 16.3% 46.9%;
        --accent: 210 40% 96.1%;
        --accent-foreground: 222.2 47.4% 11.2%;
        --destructive: 0 84.2% 60.2%;
        --destructive-foreground: 210 40% 98%;
        --border: 214.3 31.8% 91.4%;
        --input: 214.3 31.8% 91.4%;
        --ring: 238.7 83.5% 66.7%;
        --radius: 0.5rem;
    }

    .dark {
        --background: 222.2 84% 4.9%;
        --foreground: 210 40% 98%;
        --card: 222.2 84% 4.9%;
        --card-foreground: 210 40% 98%;
        --popover: 222.2 84% 4.9%;
        --popover-foreground: 210 40% 98%;
        --primary: 238.7 83.5% 66.7%;
        --primary-foreground: 222.2 47.4% 11.2%;
        --secondary: 217.2 32.6% 17.5%;
        --secondary-foreground: 210 40% 98%;
        --muted: 217.2 32.6% 17.5%;
        --muted-foreground: 215 20.2% 65.1%;
        --accent: 217.2 32.6% 17.5%;
        --accent-foreground: 210 40% 98%;
        --destructive: 0 62.8% 30.6%;
        --destructive-foreground: 210 40% 98%;
        --border: 217.2 32.6% 17.5%;
        --input: 217.2 32.6% 17.5%;
        --ring: 238.7 83.5% 66.7%;
    }
}

@layer base {
    * {
        @apply border-border;
    }
    body {
        @apply bg-background text-foreground;
    }
}

/* Status colors with dark mode support */
.status-vacant {
    @apply bg-gray-50 border-gray-200 text-gray-600;
}
.status-occupied {
    @apply bg-green-50 border-green-200 text-green-700;
}
.status-maintenance {
    @apply bg-orange-50 border-orange-200 text-orange-700;
}
.status-arrears {
    @apply bg-red-50 border-red-200 text-red-700;
}

.dark .status-vacant {
    @apply bg-gray-800 border-gray-700 text-gray-400;
}
.dark .status-occupied {
    @apply bg-green-900/30 border-green-800 text-green-400;
}
.dark .status-maintenance {
    @apply bg-orange-900/30 border-orange-800 text-orange-400;
}
.dark .status-arrears {
    @apply bg-red-900/30 border-red-800 text-red-400;
}
```

---

# PHASE 3: Utility Functions & Composables

## Step 3.1: Create cn() utility

**File**: `resources/js/lib/utils.js`

```javascript
import { clsx } from 'clsx';
import { twMerge } from 'tailwind-merge';

export function cn(...inputs) {
    return twMerge(clsx(inputs));
}
```

## Step 3.2: Create useTheme composable

**File**: `resources/js/composables/useTheme.js`

```javascript
import { ref, watch, onMounted } from 'vue';
import { useStorage } from '@vueuse/core';

export function useTheme() {
    const storedTheme = useStorage('theme', 'system');
    const isDark = ref(false);

    const updateTheme = () => {
        if (storedTheme.value === 'dark' ||
            (storedTheme.value === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
            isDark.value = true;
        } else {
            document.documentElement.classList.remove('dark');
            isDark.value = false;
        }
    };

    const setTheme = (theme) => {
        storedTheme.value = theme;
        updateTheme();
    };

    const toggleTheme = () => {
        if (storedTheme.value === 'system') {
            setTheme(isDark.value ? 'light' : 'dark');
        } else {
            setTheme(storedTheme.value === 'dark' ? 'light' : 'dark');
        }
    };

    watch(storedTheme, updateTheme);

    onMounted(() => {
        updateTheme();
        window.matchMedia('(prefers-color-scheme: dark)')
            .addEventListener('change', () => {
                if (storedTheme.value === 'system') {
                    updateTheme();
                }
            });
    });

    return { theme: storedTheme, isDark, setTheme, toggleTheme };
}
```

## Step 3.3: Export from composables index

**File**: `resources/js/composables/index.js` - Add:

```javascript
export { useTheme } from './useTheme';
```

---

# PHASE 4: Theme Toggle Component

## Step 4.1: Create ThemeToggle

**File**: `resources/js/Components/ui/theme-toggle/ThemeToggle.vue`

```vue
<script setup>
import { useTheme } from '@/composables';
import { Button } from '@/Components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu';
import { SunIcon, MoonIcon, ComputerDesktopIcon } from '@heroicons/vue/24/outline';

const { theme, setTheme, isDark } = useTheme();
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger as-child>
            <Button variant="ghost" size="icon" class="h-9 w-9">
                <SunIcon v-if="!isDark" class="h-5 w-5" />
                <MoonIcon v-else class="h-5 w-5" />
                <span class="sr-only">Toggle theme</span>
            </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end">
            <DropdownMenuItem @click="setTheme('light')">
                <SunIcon class="mr-2 h-4 w-4" />
                <span>Light</span>
            </DropdownMenuItem>
            <DropdownMenuItem @click="setTheme('dark')">
                <MoonIcon class="mr-2 h-4 w-4" />
                <span>Dark</span>
            </DropdownMenuItem>
            <DropdownMenuItem @click="setTheme('system')">
                <ComputerDesktopIcon class="mr-2 h-4 w-4" />
                <span>System</span>
            </DropdownMenuItem>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
```

## Step 4.2: Create index export

**File**: `resources/js/Components/ui/theme-toggle/index.js`

```javascript
export { default as ThemeToggle } from './ThemeToggle.vue';
```

---

# PHASE 5: Update AuthenticatedLayout

## Step 5.1: Add imports

Add to script setup:
```javascript
import ThemeToggle from '@/Components/ui/theme-toggle/ThemeToggle.vue';
```

## Step 5.2: Add dark mode classes

Update these elements in template:

| Element | Add Classes |
|---------|-------------|
| Root container `min-h-screen` | `dark:bg-gray-900` |
| Sidebar `aside` | `dark:bg-gray-800 dark:border-gray-700` |
| Top header | `dark:bg-gray-800 dark:border-gray-700` |
| Nav links | `dark:text-gray-300 dark:hover:bg-gray-700` |
| Text elements | `dark:text-gray-100`, `dark:text-gray-400` |

## Step 5.3: Add ThemeToggle to header

In the header section, before NotificationBell:
```vue
<ThemeToggle class="mr-2" />
<NotificationBell />
```

---

# PHASE 6: Complete Component Migration Map

## 6.1 Core UI Primitives (14 components)

| Current Component | shadcn Replacement | Notes |
|-------------------|-------------------|-------|
| `PrimaryButton.vue` | `Button` | Default variant |
| `SecondaryButton.vue` | `Button variant="secondary"` | |
| `DangerButton.vue` | `Button variant="destructive"` | |
| `TextInput.vue` | `Input` | |
| `InputLabel.vue` | `Label` | |
| `InputError.vue` | Keep custom or use form validation | |
| `Checkbox.vue` | `Checkbox` | |
| `Modal.vue` | `Dialog` | Base modal component |
| `SlideOutPanel.vue` | `Sheet` | Side panel |
| `Dropdown.vue` | `DropdownMenu` | |
| `DropdownLink.vue` | `DropdownMenuItem` | |
| `NavLink.vue` | Keep custom (navigation-specific) | |
| `ResponsiveNavLink.vue` | Keep custom | |
| `Pagination.vue` | `Pagination` | |

## 6.2 Business Components (19 components)

| Component | Action | Notes |
|-----------|--------|-------|
| `ActionItemCard.vue` | Use `Card` | Wrap with Card primitives |
| `ApplicationLogo.vue` | Keep custom | App-specific |
| `Breadcrumb.vue` | Use `Breadcrumb` | Add component |
| `BuildingMap.vue` | Keep custom | Domain-specific visualization |
| `BuildingWingFilter.vue` | Use `Select` | Replace dropdown |
| `FinancialSummaryCard.vue` | Use `Card` | Wrap with Card |
| `InvitationBanner.vue` | Use `Alert` | |
| `KycBadge.vue` | Use `Badge` | |
| `MetricCard.vue` | Use `Card` | |
| `NotificationBell.vue` | Use `DropdownMenu` + `Popover` | Complex component |
| `PushNotificationPrompt.vue` | Use `Alert` or `Dialog` | |
| `QuickActionsPanel.vue` | Use `Card` | |
| `SlideOutPanel.vue` | Use `Sheet` | |
| `TicketActivityTimeline.vue` | Keep custom | Domain-specific |
| `TicketFeedbackForm.vue` | Update form elements only | |
| `TicketPriorityBadge.vue` | Use `Badge` | |
| `TicketStatusBadge.vue` | Use `Badge` | |
| `TimeFilter.vue` | Use `Select` + `Popover` | |
| `UnitFilters.vue` | Use `Select` components | |

## 6.3 Finance Components (12 components)

| Component | Action | Notes |
|-----------|--------|-------|
| `DataTable.vue` | Use `Table` | Complex - needs custom wrapper |
| `VirtualDataTable.vue` | Keep custom + use `Table` styling | Performance-critical |
| `Pagination.vue` | Use `Pagination` | |
| `FilterBar.vue` | Use `Input` + `Select` + `Popover` | Complex composition |
| `ExportDropdown.vue` | Use `DropdownMenu` | |
| `AmountDisplay.vue` | Keep custom | Formatting utility |
| `MetricCard.vue` | Use `Card` | |
| `InvoiceStatusBadge.vue` | Use `Badge` with variants | |
| `PaymentMethodBadge.vue` | Use `Badge` | |
| `EmptyState.vue` | Keep custom or use Alert | |
| `ModalLoadingPlaceholder.vue` | Use `Skeleton` | Add component |
| `TabLoadingPlaceholder.vue` | Use `Skeleton` | |

## 6.4 Tab System (NEW - No existing component)

Create wrapper for consistent tab patterns across:
- Profile/Edit.vue (6 tabs)
- Settings/Index.vue (6 tabs)
- Finances/Index.vue (12+ tabs)
- Notifications/Index.vue (5 tabs)
- TenantProfileModal (5 tabs)

**File**: `resources/js/Components/ui/tabs-wrapper/TabsWrapper.vue`

## 6.5 Icons Decision

**Current**: `@heroicons/vue` (Heroicons 24px)
**shadcn default**: `lucide-vue-next` (Lucide icons)

**Recommendation**: Keep Heroicons initially, optional Lucide migration later

## Example Migrations

### Button Migration
```vue
<!-- Before -->
<PrimaryButton @click="save">Save</PrimaryButton>
<SecondaryButton @click="cancel">Cancel</SecondaryButton>
<DangerButton @click="delete">Delete</DangerButton>

<!-- After -->
<Button @click="save">Save</Button>
<Button variant="secondary" @click="cancel">Cancel</Button>
<Button variant="destructive" @click="delete">Delete</Button>
```

### Modal to Dialog
```vue
<!-- Before -->
<Modal :show="open" @close="open = false">
    <div class="p-6">
        <h2>Title</h2>
        <!-- content -->
    </div>
</Modal>

<!-- After -->
<Dialog :open="open" @update:open="open = $event">
    <DialogContent>
        <DialogHeader>
            <DialogTitle>Title</DialogTitle>
        </DialogHeader>
        <!-- content -->
        <DialogFooter>
            <Button @click="open = false">Close</Button>
        </DialogFooter>
    </DialogContent>
</Dialog>
```

### SlideOutPanel to Sheet
```vue
<!-- Before -->
<SlideOutPanel :show="open" @close="open = false" title="Panel">
    <!-- content -->
</SlideOutPanel>

<!-- After -->
<Sheet :open="open" @update:open="open = $event">
    <SheetContent side="right">
        <SheetHeader>
            <SheetTitle>Panel</SheetTitle>
        </SheetHeader>
        <!-- content -->
    </SheetContent>
</Sheet>
```

---

# PHASE 7: Complete System Migration

## System Inventory Summary
- **138 Pages** across 30+ feature folders
- **58 Components** (14 UI primitives, 44 business/domain)
- **15+ Modals** requiring Dialog migration
- **100+ files** using buttons, inputs, modals

---

## Batch 1: Auth Pages (7 pages)
- [ ] `Auth/Login.vue`
- [ ] `Auth/Register.vue`
- [ ] `Auth/ForgotPassword.vue`
- [ ] `Auth/ResetPassword.vue`
- [ ] `Auth/VerifyEmail.vue`
- [ ] `Auth/ConfirmPassword.vue`
- [ ] `Auth/TwoFactorChallenge.vue`

## Batch 2: Profile & User Settings (17 pages)
- [ ] `Profile/Edit.vue`
- [ ] `Profile/Partials/*` (8 partials)
- [ ] `Settings/Index.vue`
- [ ] `Settings/PayoutAccounts.vue`
- [ ] `Settings/Privacy.vue`
- [ ] `Settings/TwoFactor.vue`
- [ ] `Settings/TwoFactorSetup.vue`
- [ ] `Settings/TwoFactorRecoveryCodes.vue`
- [ ] `Settings/partials/*` (6 partials)

## Batch 3: Admin & Caretaker (10 pages)
- [ ] `Admin/Dashboard.vue`
- [ ] `Admin/Settings.vue`
- [ ] `Admin/Landlords.vue`
- [ ] `Admin/Users.vue`
- [ ] `Admin/AuditLogs.vue`
- [ ] `Admin/AuditLogDetail.vue`
- [ ] `Admin/BillingSettings.vue`
- [ ] `Caretaker/Dashboard.vue`
- [ ] `Caretaker/Tickets.vue`
- [ ] `ActivityLogs/Index.vue`

## Batch 4: Property & Building Management (12 pages)
- [ ] `Dashboard.vue` (main)
- [ ] `Onboarding/Index.vue`
- [ ] `Buildings/Index.vue`
- [ ] `Buildings/Edit.vue`
- [ ] `Buildings/Dashboard.vue`
- [ ] `Buildings/Show.vue`
- [ ] `Buildings/WaterSettings.vue`
- [ ] `BulkOperations/Index.vue`
- [ ] `BulkOperations/RentAdjustmentTab.vue`
- [ ] `BulkOperations/UnitStatusTab.vue`
- [ ] `BulkOperations/LeaseManagementTab.vue`
- [ ] `BulkOperations/TargetRentTab.vue`

## Batch 5: Tenant Management (17 pages)
- [ ] `Tenants/Index.vue`
- [ ] `Tenants/Show.vue`
- [ ] `Tenants/History.vue`
- [ ] `Tenants/Ledger.vue`
- [ ] `Leases/Index.vue`
- [ ] `Leases/Create.vue`
- [ ] `MoveOuts/Index.vue`
- [ ] `MoveOuts/Create.vue`
- [ ] `MoveOuts/Show.vue`
- [ ] `TenantInvitations/Index.vue`
- [ ] `TenantInvitations/Accept.vue`
- [ ] `Invitations/Index.vue`
- [ ] `Invitations/Accept.vue`
- [ ] `Invitations/AcceptExisting.vue`
- [ ] `Tenant/Dashboard.vue`
- [ ] `Tenant/Lease.vue`
- [ ] `Tenant/CompleteKyc.vue`

## Batch 6: Finance Hub (34 pages - LARGEST)
- [ ] `Finances/Hub.vue`
- [ ] `Finances/Index.vue`
- [ ] `Finances/tabs/OverviewTab.vue`
- [ ] `Finances/tabs/InvoicesTab.vue`
- [ ] `Finances/tabs/PaymentsTab.vue`
- [ ] `Finances/tabs/ArrearsTab.vue`
- [ ] `Finances/tabs/DepositsTab.vue`
- [ ] `Finances/tabs/RefundsTab.vue`
- [ ] `Finances/tabs/ExpensesTab.vue`
- [ ] `Finances/tabs/ReconciliationTab.vue`
- [ ] `Finances/tabs/ReportsTab.vue`
- [ ] `Finances/tabs/SettingsTab.vue`
- [ ] `Finances/tabs/LateFeeSettingsTab.vue`
- [ ] `Finances/tabs/TemplatesTab.vue`
- [ ] `Finances/Payments/BulkImport.vue`
- [ ] `Finances/Payments/Record.vue`
- [ ] `Finances/Refunds/Create.vue`
- [ ] `Invoices/Index.vue`
- [ ] `Invoices/Show.vue`
- [ ] `InvoiceSettings/Edit.vue`
- [ ] `InvoiceTemplates/Edit.vue`
- [ ] `ReceiptTemplates/Edit.vue`
- [ ] `CreditNotes/Index.vue`
- [ ] `CreditNotes/Show.vue`
- [ ] `CreditNotes/Create.vue`
- [ ] `PaymentVerifications/Index.vue`
- [ ] `PaymentVerifications/Show.vue`
- [ ] `TenantFinances/Index.vue`
- [ ] `TenantFinances/History.vue`
- [ ] `TenantFinances/Pay.vue`
- [ ] `Tenant/PaymentRequired.vue`
- [ ] `Tenant/Notifications.vue`
- [ ] `Subscription/Index.vue`
- [ ] `Subscription/Plans.vue`

## Batch 7: Water & Readings (4 pages)
- [ ] `Water/Settings.vue`
- [ ] `Readings/Index.vue`
- [ ] `Readings/History.vue`
- [ ] `Readings/Review.vue`

## Batch 8: Notifications & Communications (7 pages)
- [ ] `Notifications/Index.vue`
- [ ] `Notifications/partials/OverviewTab.vue`
- [ ] `Notifications/partials/ScheduledTab.vue`
- [ ] `Notifications/partials/HistoryTab.vue`
- [ ] `Notifications/partials/SettingsTab.vue`
- [ ] `Notifications/partials/TemplatesTab.vue`
- [ ] `Notifications/SetupWizard.vue`

## Batch 9: Support & Utilities (13 pages)
- [ ] `Tickets/Index.vue`
- [ ] `Tickets/Create.vue`
- [ ] `Tickets/Show.vue`
- [ ] `Documents/Index.vue`
- [ ] `Reports/Index.vue`
- [ ] `Imports/Index.vue`
- [ ] `Imports/Show.vue`
- [ ] `Verifications/Templates.vue`
- [ ] `Verifications/Conduct.vue`
- [ ] `Help/Index.vue`
- [ ] `Help/Show.vue`
- [ ] `Consent/Required.vue`
- [ ] `Landlord/Home.vue`

## Batch 10: All Modal Components (15 modals)
### Root Modals
- [ ] `Components/Modals/AddBuildingModal.vue`
- [ ] `Components/Modals/AddWingModal.vue`
- [ ] `Components/Modals/MassHikeModal.vue`
- [ ] `Components/Modals/UploadDocumentModal.vue`
- [ ] `Components/Modals/EvictionNoticeModal.vue`
- [ ] `Components/Modals/SendNotificationModal.vue`
- [ ] `Components/Modals/BulkSendNotificationModal.vue`
- [ ] `Components/Modals/TenantProfileModal.vue`

### Finance Modals
- [ ] `Finances/modals/PaymentDetailModal.vue`
- [ ] `Finances/modals/InvoiceDetailModal.vue`
- [ ] `Finances/modals/RecordPaymentModal.vue`
- [ ] `Finances/modals/RefundModal.vue`
- [ ] `Finances/modals/ForfeitDepositModal.vue`
- [ ] `Finances/modals/MatchPaymentModal.vue`
- [ ] `Finances/modals/SendRemindersModal.vue`
- [ ] `Finances/modals/RefundDepositModal.vue`

## Batch 11: Tenant Profile Tabs (5 components)
- [ ] `Components/TenantProfile/OverviewTab.vue`
- [ ] `Components/TenantProfile/DocumentsTab.vue`
- [ ] `Components/TenantProfile/HistoryTab.vue`
- [ ] `Components/TenantProfile/LeaseFinancesTab.vue`
- [ ] `Components/TenantProfile/NotesContactsTab.vue`

## Batch 12: Finance-Specific Components (12 components)
- [ ] `Components/Finances/DataTable.vue`
- [ ] `Components/Finances/VirtualDataTable.vue`
- [ ] `Components/Finances/Pagination.vue`
- [ ] `Components/Finances/FilterBar.vue`
- [ ] `Components/Finances/ExportDropdown.vue`
- [ ] `Components/Finances/AmountDisplay.vue`
- [ ] `Components/Finances/MetricCard.vue`
- [ ] `Components/Finances/InvoiceStatusBadge.vue`
- [ ] `Components/Finances/PaymentMethodBadge.vue`
- [ ] `Components/Finances/EmptyState.vue`
- [ ] `Components/Finances/ModalLoadingPlaceholder.vue`
- [ ] `Components/Finances/TabLoadingPlaceholder.vue`

---

# PHASE 8: Comprehensive Testing Checklist

## 8.1 Per-Page Testing (apply to all 138 pages)

For each migrated page, verify:

- [ ] Light mode renders correctly
- [ ] Dark mode renders correctly (toggle theme)
- [ ] All interactive states work (hover, focus, active, disabled)
- [ ] Form validation displays properly
- [ ] Form submission works
- [ ] Modals open/close correctly
- [ ] Mobile responsive (test at 375px, 768px, 1280px)
- [ ] Keyboard navigation works
- [ ] No console errors

## 8.2 Component-Specific Testing

### Buttons
- [ ] All 3 variants render correctly (primary, secondary, destructive)
- [ ] Loading states work
- [ ] Disabled states work
- [ ] Icons align properly

### Forms
- [ ] All input types work (text, email, password, number)
- [ ] Validation errors display correctly
- [ ] Labels associate with inputs
- [ ] Focus states visible in both themes

### Modals (15+ modals)
- [ ] Open/close animations smooth
- [ ] Escape key closes modal
- [ ] Backdrop click closes (where applicable)
- [ ] Body scroll locks when open
- [ ] Focus trapped inside modal

### Tables (Finance DataTable)
- [ ] Sorting works
- [ ] Row selection works
- [ ] Pagination works
- [ ] Loading states display
- [ ] Empty states display

### Tabs (multiple pages)
- [ ] Tab switching works
- [ ] Active tab styling correct
- [ ] Tab content loads properly
- [ ] URL params preserved (if applicable)

## 8.3 Status Colors Test

After dark mode is enabled, verify on Dashboard:
- [ ] Vacant units: Gray tint visible in both modes
- [ ] Occupied units: Green tint visible in both modes
- [ ] Maintenance units: Orange tint visible in both modes
- [ ] Arrears units: Red tint visible in both modes

## 8.4 Cross-Browser Testing

Test in:
- [ ] Chrome (latest)
- [ ] Firefox (latest)
- [ ] Safari (latest)
- [ ] Edge (latest)
- [ ] Mobile Safari (iOS)
- [ ] Chrome Mobile (Android)

## 8.5 Accessibility Testing

- [ ] Color contrast meets WCAG AA
- [ ] Focus indicators visible
- [ ] Screen reader announces components correctly
- [ ] Keyboard-only navigation works

## 8.6 Performance Testing

- [ ] No significant bundle size increase (check with `npm run build`)
- [ ] VirtualDataTable still performs with 1000+ rows
- [ ] Dark mode toggle is instant (no flash)
- [ ] No layout shifts during page load

---

# Critical Files Summary

| File | Action |
|------|--------|
| `tailwind.config.js` | Replace entirely |
| `vite.config.js` | Add path alias |
| `jsconfig.json` | Update paths |
| `resources/css/app.css` | Replace entirely |
| `resources/js/lib/utils.js` | Create new |
| `resources/js/composables/useTheme.js` | Create new |
| `resources/js/composables/index.js` | Add export |
| `resources/js/Components/ui/theme-toggle/` | Create new |
| `resources/js/Layouts/AuthenticatedLayout.vue` | Add dark mode + ThemeToggle |

---

# Revised Timeline (Full System)

| Phase | Description | Effort |
|-------|-------------|--------|
| 1 | Installation & Dependencies | 30 min |
| 2 | Configuration Files | 1 hour |
| 3 | Utilities & Composables | 30 min |
| 4 | Theme Toggle | 30 min |
| 5 | AuthenticatedLayout + Dark Mode | 2 hours |
| 6 | Base Component Migration | 2 hours |
| 7.1 | Batch 1-3: Auth, Profile, Admin (34 pages) | 4 hours |
| 7.2 | Batch 4-5: Buildings, Tenants (29 pages) | 4 hours |
| 7.3 | Batch 6: Finance Hub (34 pages) | 6 hours |
| 7.4 | Batch 7-9: Water, Notifications, Support (24 pages) | 3 hours |
| 7.5 | Batch 10-12: Modals & Components (32 items) | 4 hours |
| 8 | Testing & QA | 4 hours |

**Total: ~32-40 hours** (spread across multiple sessions/sprints)

## Recommended Sprint Plan

| Sprint | Focus | Pages/Components |
|--------|-------|------------------|
| Sprint 1 | Foundation + Auth | Phases 1-4 + Batch 1 |
| Sprint 2 | Settings + Admin | Batches 2-3 |
| Sprint 3 | Core Features | Batches 4-5 |
| Sprint 4 | Finance Hub | Batch 6 (largest) |
| Sprint 5 | Utilities + Modals | Batches 7-12 |
| Sprint 6 | Testing + Polish | Phase 8 |
