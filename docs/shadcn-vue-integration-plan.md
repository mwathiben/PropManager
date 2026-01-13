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
npx shadcn-vue@latest add button input label checkbox select textarea switch

# Modal/overlay components
npx shadcn-vue@latest add dialog sheet dropdown-menu popover tooltip

# Data display components
npx shadcn-vue@latest add card badge avatar table pagination

# Feedback components
npx shadcn-vue@latest add alert toast separator
```

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

# PHASE 6: Component Migration Map

## Migration Reference Table

| Current Component | shadcn Replacement | Import Statement |
|-------------------|-------------------|------------------|
| `PrimaryButton` | `Button` | `import { Button } from '@/Components/ui/button'` |
| `SecondaryButton` | `Button variant="secondary"` | Same as above |
| `DangerButton` | `Button variant="destructive"` | Same as above |
| `TextInput` | `Input` | `import { Input } from '@/Components/ui/input'` |
| `InputLabel` | `Label` | `import { Label } from '@/Components/ui/label'` |
| `Checkbox` | `Checkbox` | `import { Checkbox } from '@/Components/ui/checkbox'` |
| `Modal` | `Dialog` | `import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '@/Components/ui/dialog'` |
| `SlideOutPanel` | `Sheet` | `import { Sheet, SheetContent, SheetHeader, SheetTitle, SheetFooter } from '@/Components/ui/sheet'` |
| `Dropdown` | `DropdownMenu` | `import { DropdownMenu, DropdownMenuTrigger, DropdownMenuContent, DropdownMenuItem } from '@/Components/ui/dropdown-menu'` |
| `MetricCard` | `Card` | `import { Card, CardHeader, CardTitle, CardContent } from '@/Components/ui/card'` |
| `Pagination` | `Pagination` | `import { Pagination, ... } from '@/Components/ui/pagination'` |
| `TicketStatusBadge` | `Badge` | `import { Badge } from '@/Components/ui/badge'` |

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

# PHASE 7: Page Migration Order

## Recommended Sequence

### Batch 1: Auth Pages (Simple, Low Risk)
- [ ] `Pages/Auth/Login.vue`
- [ ] `Pages/Auth/Register.vue`
- [ ] `Pages/Auth/ForgotPassword.vue`
- [ ] `Pages/Auth/ResetPassword.vue`
- [ ] `Pages/Auth/VerifyEmail.vue`
- [ ] `Pages/Auth/ConfirmPassword.vue`

### Batch 2: Profile & Settings
- [ ] `Pages/Profile/Edit.vue`
- [ ] `Pages/Profile/Partials/UpdateProfileInformationForm.vue`
- [ ] `Pages/Profile/Partials/UpdatePasswordForm.vue`
- [ ] `Pages/Profile/Partials/DeleteUserForm.vue`

### Batch 3: Secondary Features
- [ ] `Pages/Documents/Index.vue`
- [ ] `Pages/Invitations/Index.vue`
- [ ] `Pages/Invitations/Accept.vue`
- [ ] `Pages/Readings/Index.vue`
- [ ] `Pages/Readings/History.vue`

### Batch 4: Core Features
- [ ] `Pages/Invoices/Index.vue`
- [ ] `Pages/Invoices/Show.vue`
- [ ] `Pages/Tenants/Show.vue`
- [ ] `Pages/Leases/Create.vue`

### Batch 5: Complex Pages (Last)
- [ ] `Pages/Dashboard.vue`
- [ ] `Pages/Caretaker/Dashboard.vue`
- [ ] `Pages/Buildings/Edit.vue`
- [ ] `Pages/Onboarding/Index.vue`

### Batch 6: Modal Components
- [ ] `Components/Modals/AddBuildingModal.vue`
- [ ] `Components/Modals/AddWingModal.vue`
- [ ] `Components/Modals/MassHikeModal.vue`
- [ ] `Components/Modals/UploadDocumentModal.vue`
- [ ] `Components/Modals/EvictionNoticeModal.vue`
- [ ] `Components/Modals/SendNotificationModal.vue`

---

# PHASE 8: Testing Checklist

## Per-Page Testing

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

## Status Colors Test

After dark mode is enabled, verify on Dashboard:
- [ ] Vacant units: Gray tint visible in both modes
- [ ] Occupied units: Green tint visible in both modes
- [ ] Maintenance units: Orange tint visible in both modes
- [ ] Arrears units: Red tint visible in both modes

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

# Estimated Timeline

| Phase | Description | Effort |
|-------|-------------|--------|
| 1 | Installation & Dependencies | 30 min |
| 2 | Configuration Files | 1 hour |
| 3 | Utilities & Composables | 30 min |
| 4 | Theme Toggle | 30 min |
| 5 | AuthenticatedLayout | 1 hour |
| 6 | Component Migration Reference | Reference only |
| 7 | Page Migration (all batches) | 4-8 hours |
| 8 | Testing | 2 hours |

**Total: ~10-14 hours** (spread across multiple sessions)
