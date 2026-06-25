<script setup lang="ts">
import { ref, computed } from 'vue';
import { useForm, Link } from '@inertiajs/vue3';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import LocaleSelector from '@/Components/LocaleSelector.vue';
import { useI18n } from '@/composables/useI18n';
import type { PersonalInfoTabProps } from '@/types';
import {
    UserCircleIcon,
    EnvelopeIcon,
    PhoneIcon,
    CameraIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps<PersonalInfoTabProps>();

const { t } = useI18n();

const form = useForm({
    // Laravel method spoofing: this form uploads a photo (multipart) and PHP only
    // parses multipart bodies on POST, so we POST with _method=patch rather than
    // PATCH directly. (Inertia's .post() ignores a `method` option — it must be a
    // body field, sent via forceFormData below, for the override to read it.)
    _method: 'patch',
    name: props.user.name,
    email: props.user.email,
    mobile_number: props.user.mobile_number || '',
    profile_photo: null,
});

const photoPreview = ref(props.user.profile_photo_url);
const photoInput = ref(null);

const transitionClass = 'transition ease-in-out'; /* i18n-ignore: Tailwind transition utility classes */

const selectPhoto = () => {
    photoInput.value.click();
};

const updatePhotoPreview = (event) => {
    const file = event.target.files[0];

    if (!file) return;

    if (file.size > 2 * 1024 * 1024) {
        form.errors.profile_photo = t('profile_personal_info.photo_too_large');
        return;
    }

    if (!file.type.startsWith('image/')) {
        form.errors.profile_photo = t('profile_personal_info.photo_not_image');
        return;
    }

    form.profile_photo = file;
    form.errors.profile_photo = null;

    const reader = new FileReader();
    reader.onload = (e) => {
        photoPreview.value = e.target.result;
    };
    reader.readAsDataURL(file);
};

const submit = () => {
    form.post(route('profile.update'), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            form.profile_photo = null;
        },
    });
};

const roleLabel = computed(() => {
    const labels = {
        landlord: t('profile_personal_info.roles.landlord'),
        caretaker: t('profile_personal_info.roles.caretaker'),
        tenant: t('profile_personal_info.roles.tenant'),
        super_admin: t('profile_personal_info.roles.super_admin'),
    };
    return labels[props.user.role] || props.user.role;
});
</script>

<template>
    <div class="space-y-6">
        <!-- Profile Photo Section -->
        <div class="bg-white rounded-xl border border-gray-200 p-6">
            <h3 class="text-sm font-medium text-gray-900 mb-4">{{ t('profile_personal_info.profile_photo') }}</h3>
            <div class="flex items-center gap-6">
                <div class="relative">
                    <div
                        v-if="photoPreview"
                        class="w-24 h-24 rounded-full bg-cover bg-center border-4 border-white shadow-lg"
                        :style="'background-image: url(' + photoPreview + ')'"
                    ></div>
                    <div
                        v-else
                        class="w-24 h-24 rounded-full bg-gray-100 border-4 border-white shadow-lg flex items-center justify-center"
                    >
                        <UserCircleIcon class="w-12 h-12 text-gray-400" />
                    </div>
                </div>
                <div>
                    <input
                        ref="photoInput"
                        id="profile-photo-upload"
                        type="file"
                        class="hidden"
                        accept="image/*"
                        :aria-label="t('profile_personal_info.upload_photo')"
                        @change="updatePhotoPreview"
                    />
                    <button
                        type="button"
                        @click="selectPhoto"
                        class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                    >
                        <CameraIcon class="w-5 h-5 me-2 text-gray-400" />
                        {{ photoPreview ? t('profile_personal_info.change_photo') : t('profile_personal_info.upload_photo') }}
                    </button>
                    <p class="mt-2 text-xs text-gray-500">{{ t('profile_personal_info.photo_hint') }}</p>
                </div>
            </div>
            <InputError :message="form.errors.profile_photo" class="mt-2" />
        </div>

        <!-- Personal Information Form -->
        <form @submit.prevent="submit" class="bg-white rounded-xl border border-gray-200 p-6">
            <h3 class="text-sm font-medium text-gray-900 mb-4">{{ t('profile_personal_info.personal_information') }}</h3>

            <div class="space-y-4">
                <!-- Name -->
                <div>
                    <InputLabel for="name" :value="t('profile_personal_info.full_name')" />
                    <div class="mt-1 relative">
                        <div class="absolute inset-y-0 start-0 ps-3 flex items-center pointer-events-none">
                            <UserCircleIcon class="h-5 w-5 text-gray-400" />
                        </div>
                        <TextInput
                            id="name"
                            v-model="form.name"
                            type="text"
                            class="ps-10 block w-full"
                            required
                            autocomplete="name"
                        />
                    </div>
                    <InputError :message="form.errors.name" class="mt-2" />
                </div>

                <!-- Email -->
                <div>
                    <InputLabel for="email" :value="t('profile_personal_info.email_address')" />
                    <div class="mt-1 relative">
                        <div class="absolute inset-y-0 start-0 ps-3 flex items-center pointer-events-none">
                            <EnvelopeIcon class="h-5 w-5 text-gray-400" />
                        </div>
                        <TextInput
                            id="email"
                            v-model="form.email"
                            type="email"
                            class="ps-10 block w-full"
                            required
                            autocomplete="email"
                        />
                    </div>
                    <InputError :message="form.errors.email" class="mt-2" />
                </div>

                <!-- Email Verification Notice -->
                <div v-if="mustVerifyEmail && !user.email_verified_at" class="rounded-lg bg-yellow-50 p-4">
                    <div class="flex">
                        <div class="shrink-0">
                            <svg class="h-5 w-5 text-yellow-400" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div class="ms-3">
                            <p class="text-sm text-yellow-700">
                                {{ t('profile_personal_info.email_unverified') }}
                                <Link
                                    :href="route('verification.send')"
                                    method="post"
                                    as="button"
                                    class="font-medium text-yellow-700 underline hover:text-yellow-600"
                                >
                                    {{ t('profile_personal_info.resend_verification') }}
                                </Link>
                            </p>
                            <p v-if="status === 'verification-link-sent'" class="mt-2 text-sm font-medium text-green-600">
                                {{ t('profile_personal_info.verification_link_sent') }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Phone Number -->
                <div>
                    <InputLabel for="mobile_number" :value="t('profile_personal_info.phone_number')" />
                    <div class="mt-1 relative">
                        <div class="absolute inset-y-0 start-0 ps-3 flex items-center pointer-events-none">
                            <PhoneIcon class="h-5 w-5 text-gray-400" />
                        </div>
                        <TextInput
                            id="mobile_number"
                            v-model="form.mobile_number"
                            type="tel"
                            class="ps-10 block w-full"
                            :placeholder="t('profile_personal_info.phone_placeholder')"
                        />
                    </div>
                    <InputError :message="form.errors.mobile_number" class="mt-2" />
                </div>
            </div>

            <!-- Submit -->
            <div class="mt-6 flex items-center justify-between border-t border-gray-200 pt-4">
                <p class="text-xs text-gray-500">
                    {{ t('profile_personal_info.account_type') }} <span class="font-medium text-gray-700">{{ roleLabel }}</span>
                </p>
                <div class="flex items-center gap-4">
                    <Transition
                        :enter-active-class="transitionClass"
                        enter-from-class="opacity-0"
                        :leave-active-class="transitionClass"
                        leave-to-class="opacity-0"
                    >
                        <p v-if="form.recentlySuccessful" class="text-sm text-green-600">
                            {{ t('profile_personal_info.saved') }}
                        </p>
                    </Transition>
                    <PrimaryButton :disabled="form.processing">
                        <span v-if="form.processing">{{ t('profile_personal_info.saving') }}</span>
                        <span v-else>{{ t('profile_personal_info.save_changes') }}</span>
                    </PrimaryButton>
                </div>
            </div>
        </form>

        <!--
            Phase-24 I18N-FRONT-2: language preference. Its own card —
            switching locale uses the dedicated locale.update endpoint
            (not the profile-update form), so the SetLocale middleware
            picks it up on the reload.
        -->
        <div class="bg-white rounded-xl border border-gray-200 p-6">
            <LocaleSelector />
        </div>
    </div>
</template>
