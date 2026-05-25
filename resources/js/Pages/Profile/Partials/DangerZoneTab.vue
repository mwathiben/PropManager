<script setup lang="ts">
import { ref, nextTick } from 'vue';
import { useForm } from '@inertiajs/vue3';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import DangerButton from '@/Components/DangerButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import Modal from '@/Components/Modal.vue';
import { useFormatters } from '@/composables';
import { useI18n } from '@/composables/useI18n';
import type { DangerZoneTabProps } from '@/types';
import {
    ExclamationTriangleIcon,
    TrashIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps<DangerZoneTabProps>();
const { formatDate } = useFormatters();
const { t } = useI18n();

const confirmingUserDeletion = ref(false);
const passwordInput = ref(null);

const form = useForm({
    password: '',
});

const confirmUserDeletion = () => {
    confirmingUserDeletion.value = true;
    nextTick(() => passwordInput.value?.focus());
};

const deleteUser = () => {
    form.delete(route('profile.destroy'), {
        preserveScroll: true,
        onSuccess: () => closeModal(),
        onError: () => passwordInput.value?.focus(),
        onFinish: () => form.reset(),
    });
};

const closeModal = () => {
    confirmingUserDeletion.value = false;
    form.clearErrors();
    form.reset();
};
</script>

<template>
    <div class="space-y-6">
        <!-- Warning Banner -->
        <div class="bg-red-50 border border-red-200 rounded-xl p-4">
            <div class="flex">
                <div class="shrink-0">
                    <ExclamationTriangleIcon class="h-5 w-5 text-red-400" />
                </div>
                <div class="ms-3">
                    <h3 class="text-sm font-medium text-red-800">{{ t('profile_danger_zone.banner_title') }}</h3>
                    <p class="mt-1 text-sm text-red-700">
                        {{ t('profile_danger_zone.banner_body') }}
                    </p>
                </div>
            </div>
        </div>

        <!-- Delete Account Section -->
        <div class="bg-white rounded-xl border border-red-200 p-6">
            <div class="flex items-start gap-4">
                <div class="p-2 bg-red-100 rounded-lg shrink-0">
                    <TrashIcon class="w-5 h-5 text-red-600" />
                </div>
                <div class="flex-1">
                    <h3 class="text-sm font-medium text-gray-900">{{ t('profile_danger_zone.delete_account') }}</h3>
                    <p class="mt-1 text-sm text-gray-600">
                        {{ t('profile_danger_zone.delete_account_body') }}
                    </p>

                    <div class="mt-4">
                        <DangerButton @click="confirmUserDeletion">
                            <TrashIcon class="w-4 h-4 me-2" />
                            {{ t('profile_danger_zone.delete_account') }}
                        </DangerButton>
                    </div>
                </div>
            </div>
        </div>

        <!-- Account Info -->
        <div class="bg-gray-50 rounded-xl border border-gray-200 p-4">
            <h4 class="text-xs font-medium text-gray-700 mb-3">{{ t('profile_danger_zone.account_information') }}</h4>
            <dl class="grid grid-cols-2 gap-4 text-sm">
                <div>
                    <dt class="text-gray-500">{{ t('profile_danger_zone.account_created') }}</dt>
                    <dd class="text-gray-900 font-medium">
                        {{ formatDate(user.created_at, 'long') }}
                    </dd>
                </div>
                <div>
                    <dt class="text-gray-500">{{ t('profile_danger_zone.account_type') }}</dt>
                    <dd class="text-gray-900 font-medium capitalize">{{ user.role.replace('_', ' ') }}</dd>
                </div>
            </dl>
        </div>

        <!-- Delete Confirmation Modal -->
        <Modal :show="confirmingUserDeletion" @close="closeModal">
            <div class="p-6">
                <div class="flex items-center gap-4 mb-4">
                    <div class="p-2 bg-red-100 rounded-full">
                        <ExclamationTriangleIcon class="w-6 h-6 text-red-600" />
                    </div>
                    <h2 class="text-lg font-medium text-gray-900">
                        {{ t('profile_danger_zone.modal_title') }}
                    </h2>
                </div>

                <p class="text-sm text-gray-600 mb-4">
                    {{ t('profile_danger_zone.modal_body') }}
                </p>

                <div>
                    <InputLabel for="password" :value="t('profile_danger_zone.password')" class="sr-only" />
                    <TextInput
                        id="password"
                        ref="passwordInput"
                        v-model="form.password"
                        type="password"
                        class="block w-full"
                        :placeholder="t('profile_danger_zone.password_placeholder')"
                        @keyup.enter="deleteUser"
                    />
                    <InputError :message="form.errors.password" class="mt-2" />
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton @click="closeModal">
                        {{ t('profile_danger_zone.cancel') }}
                    </SecondaryButton>

                    <DangerButton
                        :class="{ 'opacity-50': form.processing }"
                        :disabled="form.processing"
                        @click="deleteUser"
                    >
                        <span v-if="form.processing">{{ t('profile_danger_zone.deleting') }}</span>
                        <span v-else>{{ t('profile_danger_zone.delete_account') }}</span>
                    </DangerButton>
                </div>
            </div>
        </Modal>
    </div>
</template>
