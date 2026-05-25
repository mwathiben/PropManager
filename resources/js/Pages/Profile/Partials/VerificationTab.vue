<script setup lang="ts">
import { computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { useI18n } from '@/composables/useI18n';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import type { ProfileVerificationTabProps } from '@/types';
import {
    IdentificationIcon,
    UserGroupIcon,
    PhoneIcon,
    CheckCircleIcon,
    ExclamationCircleIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps<ProfileVerificationTabProps>();

const { t } = useI18n();

const form = useForm({
    mobile_number: props.user.mobile_number || '',
    national_id: props.user.national_id || '',
    emergency_contact_name: props.user.emergency_contact_name || '',
    emergency_contact_phone: props.user.emergency_contact_phone || '',
});

const submit = () => {
    form.patch(route('profile.verification.update'), {
        preserveScroll: true,
    });
};

const verificationStatus = computed(() => {
    const fields = [
        { name: 'phone', label: t('profile_verification.fields.phone'), complete: !!form.mobile_number },
        { name: 'national_id', label: t('profile_verification.fields.national_id'), complete: !!form.national_id },
        { name: 'emergency_contact', label: t('profile_verification.fields.emergency_contact'), complete: !!form.emergency_contact_name && !!form.emergency_contact_phone },
    ];

    const completed = fields.filter(f => f.complete).length;
    const hasPhoto = !!props.user.profile_photo_url;

    fields.push({ name: 'profile_photo', label: t('profile_verification.fields.profile_photo'), complete: hasPhoto });

    return {
        fields,
        completed: completed + (hasPhoto ? 1 : 0),
        total: fields.length,
        percentage: Math.round(((completed + (hasPhoto ? 1 : 0)) / fields.length) * 100),
        isComplete: props.user.kyc_completed_at !== null,
    };
});
</script>

<template>
    <div class="space-y-6">
        <!-- Verification Status Card -->
        <div class="bg-white rounded-xl border border-gray-200 p-6">
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center gap-3">
                    <div :class="[
                        'p-2 rounded-lg',
                        verificationStatus.isComplete ? 'bg-green-100' : 'bg-yellow-100'
                    ]">
                        <CheckCircleIcon v-if="verificationStatus.isComplete" class="w-5 h-5 text-green-600" />
                        <ExclamationCircleIcon v-else class="w-5 h-5 text-yellow-600" />
                    </div>
                    <div>
                        <h3 class="text-sm font-medium text-gray-900">
                            {{ verificationStatus.isComplete ? t('profile_verification.status.verified') : t('profile_verification.status.incomplete') }}
                        </h3>
                        <p class="text-xs text-gray-500">
                            {{ verificationStatus.isComplete
                                ? t('profile_verification.status.verified_body')
                                : t('profile_verification.status.incomplete_body')
                            }}
                        </p>
                    </div>
                </div>
                <span :class="['text-sm font-semibold', verificationStatus.percentage === 100 ? 'text-green-600' : 'text-indigo-600']">
                    {{ verificationStatus.percentage }}%
                </span>
            </div>

            <!-- Progress Bar -->
            <div class="w-full bg-gray-200 rounded-full h-2 mb-4">
                <div
                    :class="['h-2 rounded-full transition-all duration-300', verificationStatus.percentage === 100 ? 'bg-green-500' : 'bg-indigo-600']"
                    :style="{ width: verificationStatus.percentage + '%' }"
                ></div>
            </div>

            <!-- Field Checklist -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                <div
                    v-for="field in verificationStatus.fields"
                    :key="field.name"
                    class="flex items-center gap-2 text-sm"
                >
                    <CheckCircleIcon
                        v-if="field.complete"
                        class="w-5 h-5 text-green-500 shrink-0"
                    />
                    <ExclamationCircleIcon
                        v-else
                        class="w-5 h-5 text-gray-300 shrink-0"
                    />
                    <span :class="field.complete ? 'text-gray-700' : 'text-gray-400'">
                        {{ field.label }}
                    </span>
                </div>
            </div>
        </div>

        <!-- Verification Form -->
        <form @submit.prevent="submit" class="space-y-6">
            <!-- Identity Information -->
            <div class="bg-white rounded-xl border border-gray-200 p-6">
                <div class="flex items-center gap-3 mb-4">
                    <div class="p-2 bg-gray-100 rounded-lg">
                        <IdentificationIcon class="w-5 h-5 text-gray-600" />
                    </div>
                    <div>
                        <h3 class="text-sm font-medium text-gray-900">{{ t('profile_verification.identity.heading') }}</h3>
                        <p class="text-xs text-gray-500">{{ t('profile_verification.identity.subtitle') }}</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <!-- Phone Number -->
                    <div>
                        <InputLabel for="mobile_number" :value="t('profile_verification.identity.phone_label')" />
                        <div class="mt-1 relative">
                            <div class="absolute inset-y-0 start-0 ps-3 flex items-center pointer-events-none">
                                <PhoneIcon class="h-5 w-5 text-gray-400" />
                            </div>
                            <TextInput
                                id="mobile_number"
                                v-model="form.mobile_number"
                                type="tel"
                                class="ps-10 block w-full"
                                :placeholder="t('profile_verification.identity.phone_placeholder')"
                                required
                            />
                        </div>
                        <InputError :message="form.errors.mobile_number" class="mt-2" />
                    </div>

                    <!-- National ID -->
                    <div>
                        <InputLabel for="national_id" :value="t('profile_verification.identity.national_id_label')" />
                        <div class="mt-1 relative">
                            <div class="absolute inset-y-0 start-0 ps-3 flex items-center pointer-events-none">
                                <IdentificationIcon class="h-5 w-5 text-gray-400" />
                            </div>
                            <TextInput
                                id="national_id"
                                v-model="form.national_id"
                                type="text"
                                class="ps-10 block w-full"
                                :placeholder="t('profile_verification.identity.national_id_placeholder')"
                                required
                            />
                        </div>
                        <InputError :message="form.errors.national_id" class="mt-2" />
                    </div>
                </div>
            </div>

            <!-- Emergency Contact -->
            <div class="bg-white rounded-xl border border-gray-200 p-6">
                <div class="flex items-center gap-3 mb-4">
                    <div class="p-2 bg-gray-100 rounded-lg">
                        <UserGroupIcon class="w-5 h-5 text-gray-600" />
                    </div>
                    <div>
                        <h3 class="text-sm font-medium text-gray-900">{{ t('profile_verification.emergency.heading') }}</h3>
                        <p class="text-xs text-gray-500">{{ t('profile_verification.emergency.subtitle') }}</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <!-- Contact Name -->
                    <div>
                        <InputLabel for="emergency_contact_name" :value="t('profile_verification.emergency.name_label')" />
                        <TextInput
                            id="emergency_contact_name"
                            v-model="form.emergency_contact_name"
                            type="text"
                            class="mt-1 block w-full"
                            :placeholder="t('profile_verification.emergency.name_placeholder')"
                            required
                        />
                        <InputError :message="form.errors.emergency_contact_name" class="mt-2" />
                    </div>

                    <!-- Contact Phone -->
                    <div>
                        <InputLabel for="emergency_contact_phone" :value="t('profile_verification.emergency.phone_label')" />
                        <div class="mt-1 relative">
                            <div class="absolute inset-y-0 start-0 ps-3 flex items-center pointer-events-none">
                                <PhoneIcon class="h-5 w-5 text-gray-400" />
                            </div>
                            <TextInput
                                id="emergency_contact_phone"
                                v-model="form.emergency_contact_phone"
                                type="tel"
                                class="ps-10 block w-full"
                                :placeholder="t('profile_verification.emergency.phone_placeholder')"
                                required
                            />
                        </div>
                        <InputError :message="form.errors.emergency_contact_phone" class="mt-2" />
                    </div>
                </div>
            </div>

            <!-- Info Note -->
            <div class="bg-blue-50 border border-blue-200 rounded-xl p-4">
                <div class="flex">
                    <div class="shrink-0">
                        <svg class="h-5 w-5 text-blue-400" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div class="ms-3">
                        <h3 class="text-sm font-medium text-blue-800">{{ t('profile_verification.info.heading') }}</h3>
                        <p class="mt-1 text-sm text-blue-700">
                            {{ t('profile_verification.info.body') }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- Submit -->
            <div class="flex items-center justify-end">
                <div class="flex items-center gap-4">
                    <Transition enter-active-class="transition ease-in-out" enter-from-class="opacity-0" leave-active-class="transition ease-in-out" leave-to-class="opacity-0">
                        <p v-if="form.recentlySuccessful" class="text-sm text-green-600">
                            {{ t('profile_verification.saved') }}
                        </p>
                    </Transition>
                    <PrimaryButton :disabled="form.processing">
                        <span v-if="form.processing">{{ t('profile_verification.saving') }}</span>
                        <span v-else>{{ t('profile_verification.submit') }}</span>
                    </PrimaryButton>
                </div>
            </div>
        </form>
    </div>
</template>
