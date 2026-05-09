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
import type { DangerZoneTabProps } from '@/types';
import {
    ExclamationTriangleIcon,
    TrashIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps<DangerZoneTabProps>();
const { formatDate } = useFormatters();

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
                <div class="ml-3">
                    <h3 class="text-sm font-medium text-red-800">Danger Zone</h3>
                    <p class="mt-1 text-sm text-red-700">
                        The actions in this section are irreversible. Please proceed with caution.
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
                    <h3 class="text-sm font-medium text-gray-900">Delete Account</h3>
                    <p class="mt-1 text-sm text-gray-600">
                        Once your account is deleted, all of its resources and data will be permanently deleted.
                        Before deleting your account, please download any data or information that you wish to retain.
                    </p>

                    <div class="mt-4">
                        <DangerButton @click="confirmUserDeletion">
                            <TrashIcon class="w-4 h-4 mr-2" />
                            Delete Account
                        </DangerButton>
                    </div>
                </div>
            </div>
        </div>

        <!-- Account Info -->
        <div class="bg-gray-50 rounded-xl border border-gray-200 p-4">
            <h4 class="text-xs font-medium text-gray-700 mb-3">Account Information</h4>
            <dl class="grid grid-cols-2 gap-4 text-sm">
                <div>
                    <dt class="text-gray-500">Account Created</dt>
                    <dd class="text-gray-900 font-medium">
                        {{ formatDate(user.created_at, 'long') }}
                    </dd>
                </div>
                <div>
                    <dt class="text-gray-500">Account Type</dt>
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
                        Delete Your Account?
                    </h2>
                </div>

                <p class="text-sm text-gray-600 mb-4">
                    Once your account is deleted, all of its resources and data will be permanently deleted.
                    Please enter your password to confirm you would like to permanently delete your account.
                </p>

                <div>
                    <InputLabel for="password" value="Password" class="sr-only" />
                    <TextInput
                        id="password"
                        ref="passwordInput"
                        v-model="form.password"
                        type="password"
                        class="block w-full"
                        placeholder="Enter your password to confirm"
                        @keyup.enter="deleteUser"
                    />
                    <InputError :message="form.errors.password" class="mt-2" />
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton @click="closeModal">
                        Cancel
                    </SecondaryButton>

                    <DangerButton
                        :class="{ 'opacity-50': form.processing }"
                        :disabled="form.processing"
                        @click="deleteUser"
                    >
                        <span v-if="form.processing">Deleting...</span>
                        <span v-else>Delete Account</span>
                    </DangerButton>
                </div>
            </div>
        </Modal>
    </div>
</template>
