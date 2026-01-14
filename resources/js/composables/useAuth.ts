/**
 * Auth & Role Checking Composable
 * Provides consistent role checking across all Vue components
 */

import { computed, type ComputedRef } from 'vue';
import { usePage } from '@inertiajs/vue3';

type UserRole = 'landlord' | 'caretaker' | 'tenant' | 'super_admin';

interface User {
    id: number;
    name: string;
    email: string;
    role: UserRole;
    [key: string]: unknown;
}

interface PageProps {
    auth?: {
        user?: User;
    };
    [key: string]: unknown;
}

export interface UseAuthReturn {
    user: ComputedRef<User | undefined>;
    role: ComputedRef<UserRole | undefined>;
    isLandlord: ComputedRef<boolean>;
    isCaretaker: ComputedRef<boolean>;
    isTenant: ComputedRef<boolean>;
    isSuperAdmin: ComputedRef<boolean>;
    canManageProperty: ComputedRef<boolean>;
    canManageInvoices: ComputedRef<boolean>;
    canRecordPayments: ComputedRef<boolean>;
    canUploadDocuments: ComputedRef<boolean>;
    canDeleteDocuments: ComputedRef<boolean>;
    canManageTickets: ComputedRef<boolean>;
    canGenerateInvoices: ComputedRef<boolean>;
    canManageCaretakers: ComputedRef<boolean>;
    canViewAllBuildings: ComputedRef<boolean>;
}

export function useAuth(): UseAuthReturn {
    const page = usePage<PageProps>();

    const user = computed(() => page.props.auth?.user);
    const role = computed(() => user.value?.role);

    // Role checks
    const isLandlord = computed(() => role.value === 'landlord');
    const isCaretaker = computed(() => role.value === 'caretaker');
    const isTenant = computed(() => role.value === 'tenant');
    const isSuperAdmin = computed(() => role.value === 'super_admin');

    // Permission checks
    const canManageProperty = computed(() =>
        role.value !== undefined && ['landlord', 'super_admin'].includes(role.value)
    );

    const canManageInvoices = computed(() =>
        role.value !== undefined && ['landlord', 'super_admin'].includes(role.value)
    );

    const canRecordPayments = computed(() =>
        role.value !== undefined && ['landlord', 'super_admin'].includes(role.value)
    );

    const canUploadDocuments = computed(() =>
        role.value !== undefined && ['landlord', 'caretaker', 'super_admin'].includes(role.value)
    );

    const canDeleteDocuments = computed(() =>
        role.value !== undefined && ['landlord', 'super_admin'].includes(role.value)
    );

    const canManageTickets = computed(() =>
        role.value !== undefined && ['landlord', 'caretaker', 'super_admin'].includes(role.value)
    );

    const canGenerateInvoices = computed(() =>
        role.value !== undefined && ['landlord', 'super_admin'].includes(role.value)
    );

    const canManageCaretakers = computed(() =>
        role.value !== undefined && ['landlord', 'super_admin'].includes(role.value)
    );

    const canViewAllBuildings = computed(() =>
        role.value !== undefined && ['landlord', 'caretaker', 'super_admin'].includes(role.value)
    );

    return {
        // User & role
        user,
        role,

        // Role checks
        isLandlord,
        isCaretaker,
        isTenant,
        isSuperAdmin,

        // Permission checks
        canManageProperty,
        canManageInvoices,
        canRecordPayments,
        canUploadDocuments,
        canDeleteDocuments,
        canManageTickets,
        canGenerateInvoices,
        canManageCaretakers,
        canViewAllBuildings,
    };
}
