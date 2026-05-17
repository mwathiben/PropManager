<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useFormatters } from '@/composables';
import { useAuth } from '@/composables/useAuth';
import type { AdminUsersPageProps } from '@/types';
import {
    MagnifyingGlassIcon,
    ArrowRightOnRectangleIcon,
    CheckCircleIcon,
    XCircleIcon
} from '@heroicons/vue/24/outline';

const props = defineProps<AdminUsersPageProps>();
const { can } = useAuth();

const searchQuery = ref(props.filters?.search || '');
const selectedRole = ref(props.filters?.role || '');

const search = () => {
    router.get(route('admin.users'), {
        search: searchQuery.value,
        role: selectedRole.value,
    }, {
        preserveState: true,
        preserveScroll: true,
    });
};

const impersonate = (userId) => {
    if (confirm('This will log you in as this user. Continue?')) {
        router.post(route('admin.impersonate', userId));
    }
};

const toggleStatus = (userId) => {
    if (confirm('Are you sure you want to toggle this user\'s status?')) {
        router.post(route('admin.users.toggleStatus', userId));
    }
};

// Use composables
const { formatDate } = useFormatters();

const getRoleBadgeClass = (role) => {
    const classes = {
        super_admin: 'bg-purple-100 text-purple-800',
        landlord: 'bg-blue-100 text-blue-800',
        caretaker: 'bg-green-100 text-green-800',
        tenant: 'bg-yellow-100 text-yellow-800',
    };
    return classes[role] || 'bg-gray-100 text-gray-800';
};

const getRoleLabel = (role) => {
    return props.roles[role] || role;
};
</script>

<template>
    <Head title="Manage Users" />

    <AuthenticatedLayout>
        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="flex items-center justify-between mb-6">
                    <h1 class="text-2xl font-bold text-gray-900">All Users</h1>
                </div>

                <!-- Filters -->
                <div class="bg-white rounded-lg shadow-sm border p-4 mb-6">
                    <form @submit.prevent="search" class="flex flex-wrap gap-4">
                        <div class="relative flex-1 min-w-64">
                            <MagnifyingGlassIcon class="absolute start-3 top-1/2 -translate-y-1/2 h-5 w-5 text-gray-400" />
                            <input type="text"
                                   v-model="searchQuery"
                                   placeholder="Search by name or email..."
                                   class="w-full ps-10 pe-4 py-2 border rounded-lg focus:ring-indigo-500 focus:border-indigo-500" />
                        </div>
                        <select v-model="selectedRole"
                                class="border rounded-lg px-4 py-2 focus:ring-indigo-500 focus:border-indigo-500">
                            <option value="">All Roles</option>
                            <option v-for="(label, value) in roles" :key="value" :value="value">
                                {{ label }}
                            </option>
                        </select>
                        <button type="submit"
                                class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">
                            Filter
                        </button>
                    </form>
                </div>

                <!-- Users Table -->
                <div class="bg-white rounded-lg shadow-sm border overflow-hidden">
                    <div v-if="users.data.length === 0" class="p-8 text-center text-gray-500">
                        No users found.
                    </div>
                    <div v-else class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-start text-xs font-medium text-gray-500 uppercase">User</th>
                                    <th class="px-4 py-3 text-start text-xs font-medium text-gray-500 uppercase">Role</th>
                                    <th class="px-4 py-3 text-start text-xs font-medium text-gray-500 uppercase">Status</th>
                                    <th class="px-4 py-3 text-start text-xs font-medium text-gray-500 uppercase">Joined</th>
                                    <th class="px-4 py-3 text-start text-xs font-medium text-gray-500 uppercase">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <tr v-for="user in users.data" :key="user.id" class="hover:bg-gray-50">
                                    <td class="px-4 py-3">
                                        <div>
                                            <div class="font-medium text-gray-900">{{ user.name }}</div>
                                            <div class="text-sm text-gray-500">{{ user.email }}</div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="px-2 py-1 text-xs rounded-full" :class="getRoleBadgeClass(user.role)">
                                            {{ getRoleLabel(user.role) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center">
                                            <CheckCircleIcon v-if="user.email_verified_at" class="h-5 w-5 text-green-500 me-1" />
                                            <XCircleIcon v-else class="h-5 w-5 text-red-500 me-1" />
                                            <span :class="user.email_verified_at ? 'text-green-600' : 'text-red-600'">
                                                {{ user.email_verified_at ? 'Active' : 'Inactive' }}
                                            </span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-500">{{ formatDate(user.created_at) }}</td>
                                    <td class="px-4 py-3 text-sm">
                                        <div class="flex items-center gap-3">
                                            <button v-if="user.role !== 'super_admin'"
                                                    @click="toggleStatus(user.id)"
                                                    class="text-gray-600 hover:text-gray-900">
                                                {{ user.email_verified_at ? 'Deactivate' : 'Activate' }}
                                            </button>
                                            <button v-if="can('access-admin') && user.role !== 'super_admin'"
                                                    @click="impersonate(user.id)"
                                                    class="text-indigo-600 hover:text-indigo-900 flex items-center">
                                                <ArrowRightOnRectangleIcon class="h-4 w-4 me-1" />
                                                Login As
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div v-if="users.links && users.links.length > 3" class="px-4 py-3 border-t bg-gray-50">
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-gray-500">
                                Showing {{ users.from }} to {{ users.to }} of {{ users.total }} results
                            </span>
                            <div class="flex gap-2">
                                <Link v-if="users.prev_page_url"
                                      :href="users.prev_page_url"
                                      class="px-3 py-1 border rounded text-sm hover:bg-gray-100">
                                    Previous
                                </Link>
                                <Link v-if="users.next_page_url"
                                      :href="users.next_page_url"
                                      class="px-3 py-1 border rounded text-sm hover:bg-gray-100">
                                    Next
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
