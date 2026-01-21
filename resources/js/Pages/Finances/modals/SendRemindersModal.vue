<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';
import Modal from '@/Components/Modal.vue';
import { useFinancesStore } from '@/stores/finances';
import {
    XMarkIcon,
    BellIcon,
    CheckIcon,
    UserGroupIcon,
    EnvelopeIcon,
} from '@heroicons/vue/24/outline';

const emit = defineEmits<{
    close: [];
    success: [];
}>();

const store = useFinancesStore();

const modalData = computed(() => store.modals.sendReminders);

const success = ref(false);
const isProcessing = ref(false);
const error = ref(null);

watch(() => modalData.value.show, (newVal) => {
    if (newVal) {
        success.value = false;
        error.value = null;
    }
});

const close = () => {
    store.closeModal('sendReminders');
    emit('close');
};

const handleSend = async () => {
    isProcessing.value = true;
    error.value = null;

    router.post(route('finances.notifications.reminders'), {}, {
        preserveScroll: true,
        onSuccess: () => {
            success.value = true;
            emit('success');
            setTimeout(() => {
                close();
            }, 2000);
        },
        onError: (errs) => {
            error.value = Object.values(errs)[0] || 'Failed to send reminders';
        },
        onFinish: () => {
            isProcessing.value = false;
        },
    });
};
</script>

<template>
    <Modal :show="modalData.show" max-width="md" @close="close">
        <div v-if="success" class="p-8 text-center">
            <div class="inline-flex items-center justify-center w-16 h-16 bg-emerald-100 rounded-full mb-4">
                <CheckIcon class="w-8 h-8 text-emerald-600" />
            </div>
            <h3 class="text-lg font-semibold text-gray-900">Reminders Sent!</h3>
            <p class="text-sm text-gray-500 mt-2">Rent reminders have been queued for all active tenants.</p>
        </div>

        <template v-else>
                            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
                                <div class="flex items-center gap-3">
                                    <div class="p-2 bg-orange-100 rounded-lg">
                                        <BellIcon class="w-5 h-5 text-orange-600" />
                                    </div>
                                    <h2 class="text-lg font-semibold text-gray-900">Send Rent Reminders</h2>
                                </div>
                                <button
                                    @click="close"
                                    class="p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg transition-colors"
                                >
                                    <XMarkIcon class="w-5 h-5" />
                                </button>
                            </div>

                            <div class="p-6 space-y-4">
                                <div class="p-4 bg-orange-50 border border-orange-200 rounded-lg">
                                    <div class="flex items-start gap-3">
                                        <UserGroupIcon class="w-5 h-5 text-orange-500 shrink-0 mt-0.5" />
                                        <div>
                                            <p class="text-sm font-medium text-orange-800">Bulk Reminder</p>
                                            <p class="text-sm text-orange-700 mt-1">
                                                This will send rent reminder notifications to all active tenants with upcoming rent due.
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <div class="space-y-3">
                                    <div class="flex items-center gap-3 p-3 bg-gray-50 rounded-lg">
                                        <div class="p-2 bg-blue-100 rounded-lg">
                                            <EnvelopeIcon class="w-4 h-4 text-blue-600" />
                                        </div>
                                        <div>
                                            <p class="text-sm font-medium text-gray-900">Email Notifications</p>
                                            <p class="text-xs text-gray-500">Sent to tenant email addresses</p>
                                        </div>
                                    </div>
                                </div>

                                <div v-if="error" class="p-3 bg-red-50 border border-red-200 rounded-lg text-red-800 text-sm">
                                    {{ error }}
                                </div>

                                <div class="flex gap-3 pt-2">
                                    <button
                                        type="button"
                                        @click="close"
                                        class="flex-1 px-4 py-2.5 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors"
                                    >
                                        Cancel
                                    </button>
                                    <button
                                        @click="handleSend"
                                        :disabled="isProcessing"
                                        class="flex-1 px-4 py-2.5 text-sm font-medium text-white bg-orange-600 rounded-lg hover:bg-orange-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                                    >
                                        {{ isProcessing ? 'Sending...' : 'Send Reminders' }}
                                    </button>
                                </div>
                            </div>
        </template>
    </Modal>
</template>
