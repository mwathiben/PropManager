<script setup>
import { ref, computed, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import {
    XMarkIcon,
    PaperAirplaneIcon,
    EnvelopeIcon,
    DevicePhoneMobileIcon,
    ChatBubbleLeftRightIcon,
    BellIcon,
    UserIcon
} from '@heroicons/vue/24/outline';

const props = defineProps({
    show: Boolean,
    tenants: {
        type: Array,
        default: () => []
    },
    notificationTypes: {
        type: Array,
        default: () => []
    }
});

const emit = defineEmits(['close']);

const channels = [
    { value: 'email', label: 'Email', icon: EnvelopeIcon },
    { value: 'sms', label: 'SMS', icon: DevicePhoneMobileIcon },
    { value: 'whatsapp', label: 'WhatsApp', icon: ChatBubbleLeftRightIcon },
    { value: 'push', label: 'Push', icon: BellIcon },
];

const form = useForm({
    recipient_id: '',
    type: 'general',
    channel: 'email',
    subject: '',
    message: '',
});

const selectedTenant = computed(() => {
    return props.tenants.find(t => t.id == form.recipient_id);
});

const submit = () => {
    form.post(route('notifications.send'), {
        onSuccess: () => {
            form.reset();
            emit('close');
        }
    });
};

const close = () => {
    form.reset();
    emit('close');
};

watch(() => props.show, (newVal) => {
    if (newVal) {
        form.reset();
    }
});
</script>

<template>
    <Teleport to="body">
        <div v-if="show" class="fixed inset-0 z-50 overflow-y-auto">
            <div class="flex min-h-full items-center justify-center p-4">
                <div class="fixed inset-0 bg-gray-500/75 transition-opacity" @click="close"></div>

                <div class="relative bg-white rounded-2xl shadow-xl max-w-lg w-full">
                    <!-- Header -->
                    <div class="border-b border-gray-100 px-6 py-4 rounded-t-2xl">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <div class="p-2 bg-indigo-100 rounded-xl">
                                    <PaperAirplaneIcon class="w-5 h-5 text-indigo-600" />
                                </div>
                                <h3 class="text-lg font-semibold text-gray-900">Send Notification</h3>
                            </div>
                            <button @click="close" class="p-2 text-gray-400 hover:text-gray-600 rounded-lg">
                                <XMarkIcon class="w-5 h-5" />
                            </button>
                        </div>
                    </div>

                    <!-- Content -->
                    <form @submit.prevent="submit" class="p-6 space-y-5">
                        <!-- Recipient -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Recipient</label>
                            <select
                                v-model="form.recipient_id"
                                class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                                required
                            >
                                <option value="">Select a tenant...</option>
                                <option v-for="tenant in tenants" :key="tenant.id" :value="tenant.id">
                                    {{ tenant.name }} - {{ tenant.email }}
                                </option>
                            </select>
                            <p v-if="form.errors.recipient_id" class="text-sm text-red-600 mt-1">{{ form.errors.recipient_id }}</p>
                        </div>

                        <!-- Selected Tenant Info -->
                        <div v-if="selectedTenant" class="bg-gray-50 rounded-xl p-3 flex items-center gap-3">
                            <div class="p-2 bg-gray-200 rounded-lg">
                                <UserIcon class="w-5 h-5 text-gray-600" />
                            </div>
                            <div class="text-sm">
                                <p class="font-medium text-gray-900">{{ selectedTenant.name }}</p>
                                <p class="text-gray-500">{{ selectedTenant.email }} | {{ selectedTenant.phone || 'No phone' }}</p>
                            </div>
                        </div>

                        <!-- Type & Channel -->
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Type</label>
                                <select
                                    v-model="form.type"
                                    class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                                >
                                    <option v-for="type in notificationTypes" :key="type.value" :value="type.value">
                                        {{ type.label }}
                                    </option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Channel</label>
                                <select
                                    v-model="form.channel"
                                    class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                                >
                                    <option v-for="channel in channels" :key="channel.value" :value="channel.value">
                                        {{ channel.label }}
                                    </option>
                                </select>
                            </div>
                        </div>

                        <!-- Subject -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Subject</label>
                            <input
                                v-model="form.subject"
                                type="text"
                                class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                                placeholder="Notification subject"
                                required
                            />
                            <p v-if="form.errors.subject" class="text-sm text-red-600 mt-1">{{ form.errors.subject }}</p>
                        </div>

                        <!-- Message -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Message</label>
                            <textarea
                                v-model="form.message"
                                rows="5"
                                class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                                placeholder="Enter your message here..."
                                required
                            ></textarea>
                            <p v-if="form.errors.message" class="text-sm text-red-600 mt-1">{{ form.errors.message }}</p>
                        </div>

                        <!-- Channel indicator -->
                        <div class="flex items-center gap-2 text-sm text-gray-500">
                            <component :is="channels.find(c => c.value === form.channel)?.icon" class="w-4 h-4" />
                            <span>Will be sent via {{ form.channel }}</span>
                        </div>

                        <!-- Actions -->
                        <div class="flex justify-end gap-3 pt-4 border-t border-gray-100">
                            <button
                                type="button"
                                @click="close"
                                class="px-4 py-2 text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                :disabled="form.processing"
                                class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors disabled:opacity-50"
                            >
                                <PaperAirplaneIcon class="w-4 h-4" />
                                {{ form.processing ? 'Sending...' : 'Send Notification' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </Teleport>
</template>
