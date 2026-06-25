<script setup lang="ts">
import { ref, computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { useFormatters } from '@/composables';
import { useI18n } from '@/composables/useI18n';
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
const { t } = useI18n();
const { can } = useAuth();

const props = withDefaults(defineProps<NotificationsScheduledTabProps>(), {
    schedules: () => [],
    templates: () => [],
});

const showCreateModal = ref(false);
const editingSchedule = ref(null);

const triggerTypes = computed(() => [
    { value: 'days_before_due', label: t('notifications_scheduled.trigger_type.days_before_due.label'), description: t('notifications_scheduled.trigger_type.days_before_due.description') },
    { value: 'days_after_overdue', label: t('notifications_scheduled.trigger_type.days_after_overdue.label'), description: t('notifications_scheduled.trigger_type.days_after_overdue.description') },
    { value: 'days_before_expiry', label: t('notifications_scheduled.trigger_type.days_before_expiry.label'), description: t('notifications_scheduled.trigger_type.days_before_expiry.description') },
]);

const notificationTypes = computed(() => [
    { value: 'rent_reminder', label: t('notifications_scheduled.notification_type.rent_reminder') },
    { value: 'arrears_notice', label: t('notifications_scheduled.notification_type.arrears_notice') },
    { value: 'lease_expiry', label: t('notifications_scheduled.notification_type.lease_expiry') },
]);

const channels = computed(() => [
    { value: 'email', label: t('notifications_scheduled.channel.email') },
    { value: 'sms', label: t('notifications_scheduled.channel.sms') },
    { value: 'whatsapp', label: t('notifications_scheduled.channel.whatsapp') },
    { value: 'push', label: t('notifications_scheduled.channel.push') },
]);

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
    return props.templates.filter(tpl => tpl.type === form.type && tpl.is_active);
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
    if (confirm(t('notifications_scheduled.confirm.delete', { name: schedule.name }))) {
        useForm({}).delete(route('notifications.schedules.destroy', schedule.id));
    }
};

const toggleSchedule = (schedule) => {
    useForm({ is_active: !schedule.is_active }).put(
        route('notifications.schedules.toggle', schedule.id)
    );
};

const runScheduleNow = (schedule) => {
    if (confirm(t('notifications_scheduled.confirm.run', { name: schedule.name }))) {
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
    const found = triggerTypes.value.find(tt => tt.value === trigger);
    return found ? found.label : trigger;
};

const getTypeLabel = (type) => {
    const found = notificationTypes.value.find(nt => nt.value === type);
    return found ? found.label : type;
};

const formatNextRun = (schedule) => {
    if (!schedule.is_active) return t('notifications_scheduled.next_run.paused');
    if (schedule.next_run) {
        return formatDateTime(schedule.next_run);
    }
    return t('notifications_scheduled.next_run.calculating');
};

const formatLastRun = (schedule) => {
    if (!schedule.last_run_at) return t('notifications_scheduled.last_run.never');
    return formatDateTime(schedule.last_run_at);
};

const getChannelBadges = (channelList) => {
    return channelList.map(c => {
        const found = channels.value.find(ch => ch.value === c);
        return found ? found.label : c;
    });
};
</script>

<template>
    <div class="space-y-6">
        <!-- Header -->
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-lg font-semibold text-gray-900">{{ t('notifications_scheduled.heading') }}</h2>
                <p class="text-sm text-gray-500">{{ t('notifications_scheduled.subheading') }}</p>
            </div>
            <button
                @click="openCreateModal"
                class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors"
            >
                <PlusIcon class="w-5 h-5" />
                {{ t('notifications_scheduled.create_schedule') }}
            </button>
        </div>

        <!-- Schedules List -->
        <div v-if="schedules.length > 0" class="space-y-4">
            <div
                v-for="schedule in schedules"
                :key="schedule.id"
                :class="[ /* i18n-ignore */
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
                                :class="[ /* i18n-ignore */
                                    'px-2 py-0.5 text-xs font-medium rounded-full',
                                    schedule.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-200 text-gray-600'
                                ]"
                            >
                                {{ schedule.is_active ? t('notifications_scheduled.status.active') : t('notifications_scheduled.status.paused') }}
                            </span>
                        </div>

                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                            <div>
                                <p class="text-gray-500">{{ t('notifications_scheduled.field.type') }}</p>
                                <p class="font-medium text-gray-900">{{ getTypeLabel(schedule.type) }}</p>
                            </div>
                            <div>
                                <p class="text-gray-500">{{ t('notifications_scheduled.field.trigger') }}</p>
                                <p class="font-medium text-gray-900">
                                    {{ schedule.days_offset }} {{ getTriggerLabel(schedule.trigger) }}
                                </p>
                            </div>
                            <div>
                                <p class="text-gray-500">{{ t('notifications_scheduled.field.send_time') }}</p>
                                <p class="font-medium text-gray-900">{{ schedule.send_time }}</p>
                            </div>
                            <div>
                                <p class="text-gray-500">{{ t('notifications_scheduled.field.channels') }}</p>
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
                                {{ t('notifications_scheduled.next', { value: formatNextRun(schedule) }) }}
                            </span>
                            <span class="flex items-center gap-1">
                                <CalendarDaysIcon class="w-4 h-4" />
                                {{ t('notifications_scheduled.last', { value: formatLastRun(schedule) }) }}
                            </span>
                        </div>
                    </div>

                    <div class="flex items-center gap-1 ms-4">
                        <button
                            @click="runScheduleNow(schedule)"
                            class="p-2 text-gray-400 hover:text-green-600 hover:bg-green-50 rounded-lg transition-colors"
                            :title="t('notifications_scheduled.action.run_now')"
                        >
                            <BoltIcon class="w-5 h-5" />
                        </button>
                        <button
                            @click="toggleSchedule(schedule)"
                            :class="[ /* i18n-ignore */
                                'p-2 rounded-lg transition-colors',
                                schedule.is_active
                                    ? 'text-gray-400 hover:text-orange-600 hover:bg-orange-50'
                                    : 'text-gray-400 hover:text-green-600 hover:bg-green-50'
                            ]"
                            :title="schedule.is_active ? t('notifications_scheduled.action.pause') : t('notifications_scheduled.action.resume')"
                        >
                            <PauseIcon v-if="schedule.is_active" class="w-5 h-5" />
                            <PlayIcon v-else class="w-5 h-5" />
                        </button>
                        <button
                            @click="openEditModal(schedule)"
                            class="p-2 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-colors"
                            :title="t('notifications_scheduled.action.edit')"
                        >
                            <PencilSquareIcon class="w-5 h-5" />
                        </button>
                        <button
                            v-if="can('templates:manage')"
                            @click="deleteSchedule(schedule)"
                            class="p-2 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors"
                            :title="t('notifications_scheduled.action.delete')"
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
            <h3 class="text-lg font-semibold text-gray-900 mb-2">{{ t('notifications_scheduled.empty.title') }}</h3>
            <p class="text-gray-500 mb-4">{{ t('notifications_scheduled.empty.body') }}</p>
            <button
                @click="openCreateModal"
                class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors"
            >
                <PlusIcon class="w-5 h-5" />
                {{ t('notifications_scheduled.create_schedule') }}
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
                                    {{ editingSchedule ? t('notifications_scheduled.modal.edit_title') : t('notifications_scheduled.modal.create_title') }}
                                </h3>
                                <button @click="closeModal" class="p-2 text-gray-400 hover:text-gray-600 rounded-lg">
                                    <XMarkIcon class="w-5 h-5" />
                                </button>
                            </div>
                        </div>

                        <form @submit.prevent="saveSchedule" class="p-6 space-y-5">
                            <div>
                                <label for="sched-name" class="block text-sm font-medium text-gray-700 mb-1">{{ t('notifications_scheduled.form.name') }}</label>
                                <input
                                    id="sched-name"
                                    v-model="form.name"
                                    type="text"
                                    class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                                    :placeholder="t('notifications_scheduled.form.name_placeholder')"
                                    required
                                />
                                <p v-if="form.errors.name" class="text-sm text-red-600 mt-1">{{ form.errors.name }}</p>
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label for="sched-type" class="block text-sm font-medium text-gray-700 mb-1">{{ t('notifications_scheduled.form.notification_type') }}</label>
                                    <select
                                        id="sched-type"
                                        v-model="form.type"
                                        class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                                    >
                                        <option v-for="type in notificationTypes" :key="type.value" :value="type.value">
                                            {{ type.label }}
                                        </option>
                                    </select>
                                </div>

                                <div>
                                    <label for="sched-template" class="block text-sm font-medium text-gray-700 mb-1">{{ t('notifications_scheduled.form.template') }}</label>
                                    <select
                                        id="sched-template"
                                        v-model="form.template_id"
                                        class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                                    >
                                        <option :value="null">{{ t('notifications_scheduled.form.use_default') }}</option>
                                        <option v-for="template in filteredTemplates" :key="template.id" :value="template.id">
                                            {{ template.name }}
                                        </option>
                                    </select>
                                </div>
                            </div>

                            <div>
                                <label for="sched-trigger" class="block text-sm font-medium text-gray-700 mb-1">{{ t('notifications_scheduled.form.trigger') }}</label>
                                <select
                                    id="sched-trigger"
                                    v-model="form.trigger"
                                    class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                                >
                                    <option v-for="trigger in triggerTypes" :key="trigger.value" :value="trigger.value">
                                        {{ trigger.label }}
                                    </option>
                                </select>
                                <p class="text-xs text-gray-500 mt-1">
                                    {{ triggerTypes.find(tt => tt.value === form.trigger)?.description }}
                                </p>
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label for="sched-days-offset" class="block text-sm font-medium text-gray-700 mb-1">{{ t('notifications_scheduled.form.days') }}</label>
                                    <input
                                        id="sched-days-offset"
                                        v-model.number="form.days_offset"
                                        type="number"
                                        min="1"
                                        max="90"
                                        class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                                    />
                                    <p v-if="form.errors.days_offset" class="text-sm text-red-600 mt-1">{{ form.errors.days_offset }}</p>
                                </div>

                                <div>
                                    <label for="sched-send-time" class="block text-sm font-medium text-gray-700 mb-1">{{ t('notifications_scheduled.form.send_time') }}</label>
                                    <input
                                        id="sched-send-time"
                                        v-model="form.send_time"
                                        type="time"
                                        class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                                    />
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">{{ t('notifications_scheduled.form.channels') }}</label>
                                <div class="flex flex-wrap gap-2">
                                    <button
                                        v-for="channel in channels"
                                        :key="channel.value"
                                        type="button"
                                        @click="toggleChannel(channel.value)"
                                        :class="[ /* i18n-ignore */
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
                                <span class="text-sm text-gray-700">{{ t('notifications_scheduled.form.is_active') }}</span>
                            </div>

                            <div class="flex justify-end gap-3 pt-4 border-t border-gray-100">
                                <button
                                    type="button"
                                    @click="closeModal"
                                    class="px-4 py-2 text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors"
                                >
                                    {{ t('notifications_scheduled.action.cancel') }}
                                </button>
                                <button
                                    type="submit"
                                    :disabled="form.processing"
                                    class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors disabled:opacity-50"
                                >
                                    <CheckIcon class="w-4 h-4" />
                                    {{ editingSchedule ? t('notifications_scheduled.modal.update') : t('notifications_scheduled.modal.create') }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>
