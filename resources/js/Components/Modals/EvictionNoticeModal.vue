<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import Modal from '@/Components/Modal.vue';
import type { EvictionNoticeModalProps } from '@/types';

const props = withDefaults(defineProps<EvictionNoticeModalProps>(), {
    tenants: () => [],
    channels: () => [],
});

const emit = defineEmits(['close']);

const defaultMessage = `Hello,

This is a formal notice of eviction. Due to non-payment of rent, you are required to vacate the premises within the specified period.

Please contact your landlord immediately to discuss this matter.

Regards`;

const form = useForm({
    recipient_ids: [],
    type: 'eviction_notice',
    subject: 'Eviction Notice',
    message: defaultMessage,
    channels: ['email']
});

const selectAllTenants = () => {
    form.recipient_ids = props.tenants.map(t => t.id);
};

const deselectAllTenants = () => {
    form.recipient_ids = [];
};

const submit = () => {
    form.post(route('notifications.sendBulk'), {
        onSuccess: () => {
            form.reset();
            form.subject = 'Eviction Notice';
            form.message = defaultMessage;
            form.type = 'eviction_notice';
            emit('close');
        }
    });
};

const close = () => {
    form.reset();
    form.subject = 'Eviction Notice';
    form.message = defaultMessage;
    form.type = 'eviction_notice';
    emit('close');
};
</script>

<template>
    <Modal :show="show" max-width="2xl" @close="close">
        <div class="p-6">
            <div class="flex items-center gap-3 mb-4">
                <div class="p-2 bg-orange-100 rounded-lg">
                    <svg class="w-6 h-6 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                </div>
                <h2 class="text-xl font-bold text-gray-900">Send Eviction Notice</h2>
            </div>

            <div class="bg-orange-50 border border-orange-200 rounded-md p-3 mb-4">
                <p class="text-sm text-orange-800">
                    <strong>Warning:</strong> Eviction notices are formal legal notifications. Please ensure you have followed all legal requirements before sending.
                </p>
            </div>

            <form @submit.prevent="submit" class="space-y-4">
                <div>
                    <div class="flex justify-between items-center mb-2">
                        <label class="block text-sm font-medium text-gray-700">Select Tenants</label>
                        <div class="flex gap-2">
                            <button
                                type="button"
                                @click="selectAllTenants"
                                class="text-xs text-orange-600 hover:text-orange-800"
                            >
                                Select All
                            </button>
                            <button
                                type="button"
                                @click="deselectAllTenants"
                                class="text-xs text-gray-600 hover:text-gray-800"
                            >
                                Deselect All
                            </button>
                        </div>
                    </div>
                    <div class="border border-gray-300 rounded-md p-3 max-h-48 overflow-y-auto">
                        <div v-if="tenants.length === 0" class="text-sm text-gray-500 text-center py-4">
                            No tenants available
                        </div>
                        <div v-for="tenant in tenants" :key="tenant.id" class="flex items-center gap-2 mb-2">
                            <input
                                v-model="form.recipient_ids"
                                type="checkbox"
                                :value="tenant.id"
                                :id="`eviction-tenant-${tenant.id}`"
                                class="rounded border-gray-300 text-orange-600 focus:ring-orange-500"
                            >
                            <label :for="`eviction-tenant-${tenant.id}`" class="text-sm text-gray-700">
                                {{ tenant.name }} ({{ tenant.email }})
                            </label>
                        </div>
                    </div>
                    <p class="mt-1 text-xs text-gray-500">
                        {{ form.recipient_ids.length }} tenant(s) selected
                    </p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Channels</label>
                    <div class="flex gap-4">
                        <div v-for="channel in channels" :key="channel.value" class="flex items-center gap-2">
                            <input
                                v-model="form.channels"
                                type="checkbox"
                                :value="channel.value"
                                :id="`eviction-channel-${channel.value}`"
                                class="rounded border-gray-300 text-orange-600 focus:ring-orange-500"
                            >
                            <label :for="`eviction-channel-${channel.value}`" class="text-sm text-gray-700">
                                {{ channel.label }}
                            </label>
                        </div>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Subject</label>
                    <input
                        v-model="form.subject"
                        type="text"
                        required
                        class="w-full border-gray-300 rounded-md focus:ring-orange-500 focus:border-orange-500"
                        placeholder="Eviction Notice"
                    >
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Message</label>
                    <textarea
                        v-model="form.message"
                        rows="8"
                        required
                        class="w-full border-gray-300 rounded-md focus:ring-orange-500 focus:border-orange-500"
                        placeholder="Enter eviction notice message"
                    ></textarea>
                    <p class="mt-1 text-xs text-gray-500">
                        You can customize the message above before sending.
                    </p>
                </div>

                <div class="flex justify-end gap-2 pt-4 border-t">
                    <button
                        type="button"
                        @click="close"
                        class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300"
                    >
                        Cancel
                    </button>
                    <button
                        type="submit"
                        :disabled="form.processing || form.recipient_ids.length === 0"
                        class="px-4 py-2 bg-orange-600 text-white rounded-md hover:bg-orange-700 disabled:opacity-50"
                    >
                        {{ form.processing ? 'Sending...' : `Send to ${form.recipient_ids.length} Tenant(s)` }}
                    </button>
                </div>
            </form>
        </div>
    </Modal>
</template>
