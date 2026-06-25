<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import Modal from '@/Components/Modal.vue';
import { useI18n } from '@/composables/useI18n';
import type { EvictionNoticeModalProps } from '@/types';

const { t } = useI18n();

const props = withDefaults(defineProps<EvictionNoticeModalProps>(), {
    tenants: () => [],
    channels: () => [],
});

const emit = defineEmits(['close']);

const defaultMessage = t('eviction_notice_modal.default_message');

const form = useForm({
    recipient_ids: [],
    type: 'eviction_notice',
    subject: t('eviction_notice_modal.default_subject'),
    message: defaultMessage,
    channels: ['email']
});

const selectAllTenants = () => {
    form.recipient_ids = props.tenants.map(tenant => tenant.id);
};

const deselectAllTenants = () => {
    form.recipient_ids = [];
};

const submit = () => {
    form.post(route('notifications.sendBulk'), {
        onSuccess: () => {
            form.reset();
            form.subject = t('eviction_notice_modal.default_subject');
            form.message = defaultMessage;
            form.type = 'eviction_notice';
            emit('close');
        }
    });
};

const close = () => {
    form.reset();
    form.subject = t('eviction_notice_modal.default_subject');
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
                <h2 class="text-xl font-bold text-gray-900">{{ t('eviction_notice_modal.heading') }}</h2>
            </div>

            <div class="bg-orange-50 border border-orange-200 rounded-md p-3 mb-4">
                <p class="text-sm text-orange-800">
                    <strong>{{ t('eviction_notice_modal.warning_label') }}</strong> {{ t('eviction_notice_modal.warning_body') }}
                </p>
            </div>

            <form @submit.prevent="submit" class="space-y-4">
                <div>
                    <div class="flex justify-between items-center mb-2">
                        <label class="block text-sm font-medium text-gray-700">{{ t('eviction_notice_modal.select_tenants') }}</label>
                        <div class="flex gap-2">
                            <button
                                type="button"
                                @click="selectAllTenants"
                                class="text-xs text-orange-600 hover:text-orange-800"
                            >
                                {{ t('eviction_notice_modal.select_all') }}
                            </button>
                            <button
                                type="button"
                                @click="deselectAllTenants"
                                class="text-xs text-gray-600 hover:text-gray-800"
                            >
                                {{ t('eviction_notice_modal.deselect_all') }}
                            </button>
                        </div>
                    </div>
                    <div class="border border-gray-300 rounded-md p-3 max-h-48 overflow-y-auto">
                        <div v-if="tenants.length === 0" class="text-sm text-gray-500 text-center py-4">
                            {{ t('eviction_notice_modal.no_tenants') }}
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
                        {{ t('eviction_notice_modal.tenants_selected', { count: form.recipient_ids.length }) }}
                    </p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('eviction_notice_modal.channels') }}</label>
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
                    <label for="eviction-subject" class="block text-sm font-medium text-gray-700 mb-1">{{ t('eviction_notice_modal.subject') }}</label>
                    <input
                        id="eviction-subject"
                        v-model="form.subject"
                        type="text"
                        required
                        class="w-full border-gray-300 rounded-md focus:ring-orange-500 focus:border-orange-500"
                        :placeholder="t('eviction_notice_modal.subject_placeholder')"
                    >
                </div>

                <div>
                    <label for="eviction-message" class="block text-sm font-medium text-gray-700 mb-1">{{ t('eviction_notice_modal.message') }}</label>
                    <textarea
                        id="eviction-message"
                        v-model="form.message"
                        rows="8"
                        required
                        class="w-full border-gray-300 rounded-md focus:ring-orange-500 focus:border-orange-500"
                        :placeholder="t('eviction_notice_modal.message_placeholder')"
                    ></textarea>
                    <p class="mt-1 text-xs text-gray-500">
                        {{ t('eviction_notice_modal.message_hint') }}
                    </p>
                </div>

                <div class="flex justify-end gap-2 pt-4 border-t">
                    <button
                        type="button"
                        @click="close"
                        class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300"
                    >
                        {{ t('eviction_notice_modal.cancel') }}
                    </button>
                    <button
                        type="submit"
                        :disabled="form.processing || form.recipient_ids.length === 0"
                        class="px-4 py-2 bg-orange-600 text-white rounded-md hover:bg-orange-700 disabled:opacity-50"
                    >
                        {{ form.processing ? t('eviction_notice_modal.sending') : t('eviction_notice_modal.send_to_count', { count: form.recipient_ids.length }) }}
                    </button>
                </div>
            </form>
        </div>
    </Modal>
</template>
