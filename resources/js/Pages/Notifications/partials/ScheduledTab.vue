<script setup lang="ts">
import { ref, computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { useFormatters } from '@/composables';
import { useAuth } from '@/composables/useAuth';
import {
    PlusIcon,
    PencilSquareIcon,
    TrashIcon,
    PlayIcon,
    PauseIcon,
    ClockIcon,
    CalendarDaysIcon,
    XMarkIcon,
    CheckIcon,
    BoltIcon
} from '@heroicons/vue/24/outline';
import type { NotificationsScheduledTabProps } from '@/types';

const { formatDateTime } = useFormatters();
const { can } = useAuth();

const props = withDefaults(defineProps<NotificationsScheduledTabProps>(), {
    schedules: () => [],
    templates: () => [],
});

const showCreateModal = ref(false);
const editingSchedule = ref(null);

const triggerTypes = [
    { value: 'days_before_due', label: 'Days Before Rent Due', description: 'Send X days before the rent due date' },
    { value: 'days_after_overdue', label: 'Days After Overdue', description: 'Send X days after rent becomes overdue' },
    { value: 'days_before_expiry', label: 'Days Before Lease Expiry', description: 'Send X days before lease expires' },
];

const notificationTypes = [
    { value: 'rent_reminder', label: 'Rent Reminder' },
    { value: 'arrears_notice', label: 'Arrears Notice' },
    { value: 'lease_expiry', label: 'Lease Expiry' },
];

const channels = [
    { value: 'email', label: 'Email' },
    { value: 'sms', label: 'SMS' },
    { value: 'whatsapp', label: 'WhatsApp' },
    { value: 'push', label: 'Push' },
];

const form = useForm({
    id: null,
    name: '',
    type: 'rent_reminder',
    trigger: 'days_before_due',
    days_offset: 3,
    send_time: '09:00',
    channels: ['email'],
    template_id: null,
    is_active: true,
});

const filteredTemplates = computed(() => {
    return props.templates.filter(t => t.type === form.type && t.is_active);
});

const openCreateModal = () => {
    form.reset();
    form.id = null;
    form.channels = ['email'];
    editingSchedule.value = null;
    showCreateModal.value = true;
};

const openEditModal = (schedule) => {
    editingSchedule.value = schedule;
    form.id = schedule.id;
    form.name = schedule.name;
    form.type = schedule.type;
    form.trigger = schedule.trigger;
    form.days_offset = schedule.days_offset;
    form.send_time = schedule.send_time;
    form.channels = schedule.channels || ['email'];
    form.template_id = schedule.template_id;
    form.is_active = schedule.is_active;
    showCreateModal.value = true;
};

const closeModal = () => {
    showCreateModal.value = false;
    editingSchedule.value = null;
    form.reset();
};

const saveSchedule = () => {
    if (form.id) {
        form.put(route('notifications.schedules.update', form.id), {
            onSuccess: () => closeModal(),
        });
    } else {
        form.post(route('notifications.schedules.store'), {
            onSuccess: () => closeModal(),
        });
    }
};

const deleteSchedule = (schedule) => {
    if (confirm(`Are you sure you want to delete "${schedule.name}"?`)) {
        useForm({}).delete(route('notifications.schedules.destroy', schedule.id));
    }
};

const toggleSchedule = (schedule) => {
    useForm({ is_active: !schedule.is_active }).put(
        route('notifications.schedules.toggle', schedule.id)
    );
};

const runScheduleNow = (schedule) => {
    if (confirm(`Run "${schedule.name}" now? This will send notifications to all matching tenants.`)) {
        useForm({}).post(route('notifications.schedules.run', schedule.id));
    }
};

const toggleChannel = (channel) => {
    const index = form.channels.indexOf(channel);
    if (index > -1) {
        if (form.channels.length > 1) {
            form.channels.splice(index, 1);
        }
    } else {
        form.channels.push(channel);
    }
};

const getTriggerLabel = (trigger) => {
    const found = triggerTypes.find(t => t.value === trigger);
    return found ? found.label : trigger;
};

const getTypeLabel = (type) => {
    const found = notificationTypes.find(t => t.value === type);
    return found ? found.label : type;
};

const formatNextRun = (schedule) => {
    if (!schedule.is_active) return 'Paused';
    if (schedule.next_run) {
        return formatDateTime(schedule.next_run);
    }
    return 'Calculating...';
};

const formatLastRun = (schedule) => {
    if (!schedule.last_run_at) return 'Never';
    return formatDateTime(schedule.last_run_at);
};

const getChannelBadges = (channelList) => {
    return channelList.map(c => {
        const found = channels.find(ch => ch.value === c);
        return found ? found.label : c;
    });
};
</script>

<template>
    <div class="space-y-6">
        <!-- Header -->
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-lg font-semibold text-gray-900">Scheduled Notifications</h2>
                <p class="text-sm text-gray-500">Automate rent reminders, arrears notices, and lease expiry alerts</p>
            </div>
            <button
                @click="openCreateModal"
                class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors"
            >
                <PlusIcon class="w-5 h-5" />
                Create Schedule
            </button>
        </div>

        <!-- Schedules List -->
        <div v-if="schedules.length > 0" class="space-y-4">
            <div
                v-for="schedule in schedules"
                :key="schedule.id"
                :class="[
                    'bg-white rounded-2xl shadow-sm border p-5 transition-all',
                    schedule.is_active ? 'border-gray-100' : 'border-gray-200 bg-gray-50'
                ]"
            >
                <div class="flex items-start justify-between">
                    <div class="flex-1">
                        <div class="flex items-center gap-3 mb-2">
                            <h3 :class="['font-semibold', schedule.is_active ? 'text-gray-900' : 'text-gray-500']">
                                {{ schedule.name }}
                            </h3>
                            <span
                                :class="[
                                    'px-2 py-0.5 text-xs font-medium rounded-full',
                                    schedule.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-200 text-gray-600'
                                ]"
                            >
                                {{ schedule.is_active ? 'Active' : 'Paused' }}
                            </span>
                        </div>

                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                            <div>
                                <p class="text-gray-500">Type</p>
                                <p class="font-medium text-gray-900">{{ getTypeLabel(schedule.type) }}</p>
                            </div>
                            <div>
                                <p class="text-gray-500">Trigger</p>
                                <p class="font-medium text-gray-900">
                                    {{ schedule.days_offset }} {{ getTriggerLabel(schedule.trigger) }}
                                </p>
                            </div>
                            <div>
                                <p class="text-gray-500">Send Time</p>
                                <p class="font-medium text-gray-900">{{ schedule.send_time }}</p>
                            </div>
                            <div>
                                <p class="text-gray-500">Channels</p>
                                <div class="flex flex-wrap gap-1 mt-0.5">
                                    <span
                                        v-for="channel in getChannelBadges(schedule.channels)"
                                        :key="channel"
                                        class="px-1.5 py-0.5 text-xs bg-indigo-100 text-indigo-700 rounded"
                                    >
                                        {{ channel }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center gap-6 mt-3 pt-3 border-t border-gray-100 text-xs text-gray-500">
                            <span class="flex items-center gap-1">
                                <ClockIcon class="w-4 h-4" />
                                Next: {{ formatNextRun(schedule) }}
                            </span>
                            <span class="flex items-center gap-1">
                                <CalendarDaysIcon class="w-4 h-4" />
                                Last: {{ formatLastRun(schedule) }}
                            </span>
                        </div>
                    </div>

                    <div class="flex items-center gap-1 ms-4">
                        <button
                            @click="runScheduleNow(schedule)"
                            class="p-2 text-gray-400 hover:text-green-600 hover:bg-green-50 rounded-lg transition-colors"
                            title="Run Now"
                        >
                            <BoltIcon class="w-5 h-5" />
                        </button>
                        <button
                            @click="toggleSchedule(schedule)"
                            :class="[
                                'p-2 rounded-lg transition-colors',
                                schedule.is_active
                                    ? 'text-gray-400 hover:text-orange-600 hover:bg-orange-50'
                                    : 'text-gray-400 hover:text-green-600 hover:bg-green-50'
                            ]"
                            :title="schedule.is_active ? 'Pause' : 'Resume'"
                        >
                            <PauseIcon v-if="schedule.is_active" class="w-5 h-5" />
                            <PlayIcon v-else class="w-5 h-5" />
                        </button>
                        <button
                            @click="openEditModal(schedule)"
                            class="p-2 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-colors"
                            title="Edit"
                        >
                            <PencilSquareIcon class="w-5 h-5" />
                        </button>
                        <button
                            v-if="can('templates:manage')"
                            @click="deleteSchedule(schedule)"
                            class="p-2 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors"
                            title="Delete"
                        >
                            <TrashIcon class="w-5 h-5" />
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Empty State -->
        <div v-else class="bg-white rounded-2xl shadow-sm border border-gray-100 p-12 text-center">
            <div class="p-4 bg-indigo-100 rounded-full w-16 h-16 mx-auto mb-4 flex items-center justify-center">
                <CalendarDaysIcon class="w-8 h-8 text-indigo-600" />
            </div>
            <h3 class="text-lg font-semibold text-gray-900 mb-2">No Schedules Yet</h3>
            <p class="text-gray-500 mb-4">Create automated notification schedules to keep tenants informed</p>
            <button
                @click="openCreateModal"
                class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors"
            >
                <PlusIcon class="w-5 h-5" />
                Create Schedule
            </button>
        </div>

        <!-- Create/Edit Modal -->
        <Teleport to="body">
            <div v-if="showCreateModal" class="fixed inset-0 z-50 overflow-y-auto">
                <div class="flex min-h-full items-center justify-center p-4">
                    <div class="fixed inset-0 bg-gray-900/50 z-40 transition-opacity" @click="closeModal"></div>

                    <div class="relative z-50 bg-white rounded-2xl shadow-xl max-w-lg w-full max-h-[90vh] overflow-y-auto">
                        <div class="sticky top-0 bg-white border-b border-gray-100 px-6 py-4 rounded-t-2xl">
                            <div class="flex items-center justify-between">
                                <h3 class="text-lg font-semibold text-gray-900">
                                    {{ editingSchedule ? 'Edit Schedule' : 'Create Schedule' }}
                                </h3>
                                <button @click="closeModal" class="p-2 text-gray-400 hover:text-gray-600 rounded-lg">
                                    <XMarkIcon class="w-5 h-5" />
                                </button>
                            </div>
                        </div>

                        <form @submit.prevent="saveSchedule" class="p-6 space-y-5">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Schedule Name</label>
                                <input
                                    v-model="form.name"
                                    type="text"
                                    class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                                    placeholder="e.g., 3-Day Rent Reminder"
                                    required
                                />
                                <p v-if="form.errors.name" class="text-sm text-red-600 mt-1">{{ form.errors.name }}</p>
                            </div>

                            <div class="grid grid-cols-2 gap-4">
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

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Template (Optional)</label>
                                    <select
                                        v-model="form.template_id"
                                        class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                                    >
                                        <option :value="null">Use default</option>
                                        <option v-for="template in filteredTemplates" :key="template.id" :value="template.id">
                                            {{ template.name }}
                                        </option>
                                    </select>
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Trigger</label>
                                <select
                                    v-model="form.trigger"
                                    class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                                >
                                    <option v-for="trigger in triggerTypes" :key="trigger.value" :value="trigger.value">
                                        {{ trigger.label }}
                                    </option>
                                </select>
                                <p class="text-xs text-gray-500 mt-1">
                                    {{ triggerTypes.find(t => t.value === form.trigger)?.description }}
                                </p>
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Days</label>
                                    <input
                                        v-model.number="form.days_offset"
                                        type="number"
                                        min="1"
                                        max="90"
                                        class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                                    />
                                    <p v-if="form.errors.days_offset" class="text-sm text-red-600 mt-1">{{ form.errors.days_offset }}</p>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Send Time</label>
                                    <input
                                        v-model="form.send_time"
                                        type="time"
                                        class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                                    />
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Channels</label>
                                <div class="flex flex-wrap gap-2">
                                    <button
                                        v-for="channel in channels"
                                        :key="channel.value"
                                        type="button"
                                        @click="toggleChannel(channel.value)"
                                        :class="[
                                            'px-4 py-2 rounded-lg text-sm font-medium transition-all',
                                            form.channels.includes(channel.value)
                                                ? 'bg-indigo-600 text-white'
                                                : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
                                        ]"
                                    >
                                        {{ channel.label }}
                                    </button>
                                </div>
                                <p v-if="form.errors.channels" class="text-sm text-red-600 mt-1">{{ form.errors.channels }}</p>
                            </div>

                            <div class="flex items-center gap-3">
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input type="checkbox" v-model="form.is_active" class="sr-only peer" />
                                    <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-indigo-300 rounded-full peer peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600"></div>
                                </label>
                                <span class="text-sm text-gray-700">Schedule is active</span>
                            </div>

                            <div class="flex justify-end gap-3 pt-4 border-t border-gray-100">
                                <button
                                    type="button"
                                    @click="closeModal"
                                    class="px-4 py-2 text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    :disabled="form.processing"
                                    class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors disabled:opacity-50"
                                >
                                    <CheckIcon class="w-4 h-4" />
                                    {{ editingSchedule ? 'Update Schedule' : 'Create Schedule' }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>
