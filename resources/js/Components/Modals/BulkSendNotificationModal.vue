<script setup>
import { ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import {
    XMarkIcon,
    UsersIcon,
    EnvelopeIcon,
    DevicePhoneMobileIcon,
    ChatBubbleLeftRightIcon,
    BellIcon,
    CheckIcon
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
    },
    channels: {
        type: Array,
        default: () => []
    }
});

const emit = defineEmits(['close']);

const channelIcons = {
    email: EnvelopeIcon,
    sms: DevicePhoneMobileIcon,
    whatsapp: ChatBubbleLeftRightIcon,
    push: BellIcon,
};

const form = useForm({
    recipient_ids: [],
    type: 'general',
    subject: '',
    message: '',
    channels: ['email']
});

const selectAllTenants = () => {
    form.recipient_ids = props.tenants.map(t => t.id);
};

const deselectAllTenants = () => {
    form.recipient_ids = [];
};

const toggleChannel = (channelValue) => {
    const index = form.channels.indexOf(channelValue);
    if (index > -1) {
        if (form.channels.length > 1) {
            form.channels.splice(index, 1);
        }
    } else {
        form.channels.push(channelValue);
    }
};

const submit = () => {
    form.post(route('notifications.sendBulk'), {
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
        form.channels = ['email'];
    }
});
</script>

<template>
    <Teleport to="body">
        <div v-if="show" class="fixed inset-0 z-50 overflow-y-auto">
            <div class="flex min-h-full items-center justify-center p-4">
                <div class="fixed inset-0 bg-gray-500/75 transition-opacity" @click="close"></div>

                <div class="relative bg-white rounded-2xl shadow-xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
                    <!-- Header -->
                    <div class="sticky top-0 bg-white border-b border-gray-100 px-6 py-4 rounded-t-2xl z-10">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <div class="p-2 bg-purple-100 rounded-xl">
                                    <UsersIcon class="w-5 h-5 text-purple-600" />
                                </div>
                                <h3 class="text-lg font-semibold text-gray-900">Bulk Send Notification</h3>
                            </div>
                            <button @click="close" class="p-2 text-gray-400 hover:text-gray-600 rounded-lg">
                                <XMarkIcon class="w-5 h-5" />
                            </button>
                        </div>
                    </div>

                    <!-- Content -->
                    <form @submit.prevent="submit" class="p-6 space-y-5">
                        <!-- Recipients -->
                        <div>
                            <div class="flex justify-between items-center mb-2">
                                <label class="block text-sm font-medium text-gray-700">Recipients</label>
                                <div class="flex gap-3">
                                    <button
                                        type="button"
                                        @click="selectAllTenants"
                                        class="text-sm text-indigo-600 hover:text-indigo-800 font-medium"
                                    >
                                        Select All
                                    </button>
                                    <button
                                        type="button"
                                        @click="deselectAllTenants"
                                        class="text-sm text-gray-500 hover:text-gray-700"
                                    >
                                        Clear
                                    </button>
                                </div>
                            </div>
                            <div class="border border-gray-200 rounded-xl p-3 max-h-48 overflow-y-auto bg-gray-50">
                                <div v-if="tenants.length === 0" class="text-center py-4 text-gray-500 text-sm">
                                    No tenants available
                                </div>
                                <div
                                    v-for="tenant in tenants"
                                    :key="tenant.id"
                                    @click="form.recipient_ids.includes(tenant.id) ? form.recipient_ids.splice(form.recipient_ids.indexOf(tenant.id), 1) : form.recipient_ids.push(tenant.id)"
                                    :class="[
                                        'flex items-center gap-3 p-2 rounded-lg cursor-pointer transition-colors mb-1',
                                        form.recipient_ids.includes(tenant.id)
                                            ? 'bg-indigo-100 border border-indigo-200'
                                            : 'hover:bg-gray-100'
                                    ]"
                                >
                                    <div :class="[
                                        'w-5 h-5 rounded flex items-center justify-center border-2 transition-colors',
                                        form.recipient_ids.includes(tenant.id)
                                            ? 'bg-indigo-600 border-indigo-600'
                                            : 'border-gray-300 bg-white'
                                    ]">
                                        <CheckIcon v-if="form.recipient_ids.includes(tenant.id)" class="w-3 h-3 text-white" />
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="font-medium text-gray-900 text-sm truncate">{{ tenant.name }}</p>
                                        <p class="text-xs text-gray-500 truncate">{{ tenant.email }}</p>
                                    </div>
                                </div>
                            </div>
                            <p class="mt-2 text-sm font-medium" :class="form.recipient_ids.length > 0 ? 'text-indigo-600' : 'text-gray-500'">
                                {{ form.recipient_ids.length }} tenant(s) selected
                            </p>
                        </div>

                        <!-- Type -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Notification Type</label>
                            <select
                                v-model="form.type"
                                class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                            >
                                <option v-for="type in notificationTypes" :key="type.value" :value="type.value">
                                    {{ type.label }}
                                </option>
                            </select>
                        </div>

                        <!-- Channels -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Channels</label>
                            <div class="flex flex-wrap gap-2">
                                <button
                                    v-for="channel in channels"
                                    :key="channel.value"
                                    type="button"
                                    @click="toggleChannel(channel.value)"
                                    :class="[
                                        'inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium transition-all',
                                        form.channels.includes(channel.value)
                                            ? 'bg-indigo-600 text-white'
                                            : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
                                    ]"
                                >
                                    <component :is="channelIcons[channel.value]" class="w-4 h-4" />
                                    {{ channel.label }}
                                </button>
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

                        <!-- Summary -->
                        <div class="bg-gray-50 rounded-xl p-4">
                            <p class="text-sm text-gray-600">
                                This notification will be sent to
                                <span class="font-semibold text-gray-900">{{ form.recipient_ids.length }} tenant(s)</span>
                                via
                                <span class="font-semibold text-gray-900">{{ form.channels.join(', ') }}</span>
                            </p>
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
                                :disabled="form.processing || form.recipient_ids.length === 0"
                                class="inline-flex items-center gap-2 px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors disabled:opacity-50"
                            >
                                <UsersIcon class="w-4 h-4" />
                                {{ form.processing ? 'Sending...' : `Send to ${form.recipient_ids.length} Recipient(s)` }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </Teleport>
</template>
