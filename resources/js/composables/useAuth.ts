/**
 * Auth & Role Checking Composable
 * Provides consistent role checking across all Vue components
 */

import { computed, type ComputedRef } from 'vue';
import { usePage } from '@inertiajs/vue3';

type UserRole = 'landlord' | 'caretaker' | 'tenant' | 'super_admin' | 'water_client' | 'owner';

/**
 * Phase-20 AUTHZ-FRONT-6: slim DTO mirror of App\Support\UserDto.
 * Adding a field here? Update UserDto::from() in the same commit.
 */
interface User {
    id: number;
    name: string;
    email: string;
    role: UserRole;
    landlord_id: number | null;
    profile_photo_url: string | null;
    is_restricted: boolean;
    abilities: Record<string, boolean>;
}

interface PageProps {
    auth?: {
        user?: User | null;
    };
    [key: string]: unknown;
}

export interface UseAuthReturn {
    user: ComputedRef<User | undefined | null>;
    role: ComputedRef<UserRole | undefined>;
    isLandlord: ComputedRef<boolean>;
    isCaretaker: ComputedRef<boolean>;
    isTenant: ComputedRef<boolean>;
    isSuperAdmin: ComputedRef<boolean>;
    isOwner: ComputedRef<boolean>;
    isRestricted: ComputedRef<boolean>;
    can: (ability: string) => boolean;
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
    const isOwner = computed(() => role.value === 'owner');

    // Phase-20 AUTHZ-FRONT-4: DPA-4 restricted-user surface for the UI.
    const isRestricted = computed(() => user.value?.is_restricted === true);

    /**
     * Phase-20 AUTHZ-FRONT-1: ability check from the shared abilities map.
     * Falls back to false if the ability isn't in the map (fail-closed).
     * Prefer this over the legacy canX computed properties below — the
     * canX properties replicate role logic client-side, which drifts
     * from the server-side Gate decisions.
     */
    const can = (ability: string): boolean => {
        return user.value?.abilities?.[ability] === true;
    };

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
        isOwner,
        isRestricted,

        // Ability check (Phase-20 AUTHZ-FRONT-1)
        can,

        // Permission checks (legacy role-derived — prefer can() above)
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
