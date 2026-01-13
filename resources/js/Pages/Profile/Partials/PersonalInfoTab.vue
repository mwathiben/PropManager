<script setup>
import { ref, computed } from 'vue';
import { useForm, Link } from '@inertiajs/vue3';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import {
    UserCircleIcon,
    EnvelopeIcon,
    PhoneIcon,
    CameraIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps({
    user: Object,
    mustVerifyEmail: Boolean,
    status: String,
});

const form = useForm({
    name: props.user.name,
    email: props.user.email,
    mobile_number: props.user.mobile_number || '',
    profile_photo: null,
});

const photoPreview = ref(props.user.profile_photo_url);
const photoInput = ref(null);

const selectPhoto = () => {
    photoInput.value.click();
};

const updatePhotoPreview = (event) => {
    const file = event.target.files[0];

    if (!file) return;

    if (file.size > 2 * 1024 * 1024) {
        form.errors.profile_photo = 'Photo must not exceed 2MB';
        return;
    }

    if (!file.type.startsWith('image/')) {
        form.errors.profile_photo = 'File must be an image';
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
        method: 'patch',
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            form.profile_photo = null;
        },
    });
};

const roleLabel = computed(() => {
    const labels = {
        landlord: 'Landlord',
        caretaker: 'Caretaker',
        tenant: 'Tenant',
        super_admin: 'Super Admin',
    };
    return labels[props.user.role] || props.user.role;
});
</script>

<template>
    <div class="space-y-6">
        <!-- Profile Photo Section -->
        <div class="bg-white rounded-xl border border-gray-200 p-6">
            <h3 class="text-sm font-medium text-gray-900 mb-4">Profile Photo</h3>
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
                        type="file"
                        class="hidden"
                        accept="image/*"
                        @change="updatePhotoPreview"
                    />
                    <button
                        type="button"
                        @click="selectPhoto"
                        class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                    >
                        <CameraIcon class="w-5 h-5 mr-2 text-gray-400" />
                        {{ photoPreview ? 'Change Photo' : 'Upload Photo' }}
                    </button>
                    <p class="mt-2 text-xs text-gray-500">JPG, PNG or GIF. Max 2MB.</p>
                </div>
            </div>
            <InputError :message="form.errors.profile_photo" class="mt-2" />
        </div>

        <!-- Personal Information Form -->
        <form @submit.prevent="submit" class="bg-white rounded-xl border border-gray-200 p-6">
            <h3 class="text-sm font-medium text-gray-900 mb-4">Personal Information</h3>

            <div class="space-y-4">
                <!-- Name -->
                <div>
                    <InputLabel for="name" value="Full Name" />
                    <div class="mt-1 relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <UserCircleIcon class="h-5 w-5 text-gray-400" />
                        </div>
                        <TextInput
                            id="name"
                            v-model="form.name"
                            type="text"
                            class="pl-10 block w-full"
                            required
                            autocomplete="name"
                        />
                    </div>
                    <InputError :message="form.errors.name" class="mt-2" />
                </div>

                <!-- Email -->
                <div>
                    <InputLabel for="email" value="Email Address" />
                    <div class="mt-1 relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <EnvelopeIcon class="h-5 w-5 text-gray-400" />
                        </div>
                        <TextInput
                            id="email"
                            v-model="form.email"
                            type="email"
                            class="pl-10 block w-full"
                            required
                            autocomplete="email"
                        />
                    </div>
                    <InputError :message="form.errors.email" class="mt-2" />
                </div>

                <!-- Email Verification Notice -->
                <div v-if="mustVerifyEmail && !user.email_verified_at" class="rounded-lg bg-yellow-50 p-4">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-yellow-400" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm text-yellow-700">
                                Your email address is unverified.
                                <Link
                                    :href="route('verification.send')"
                                    method="post"
                                    as="button"
                                    class="font-medium text-yellow-700 underline hover:text-yellow-600"
                                >
                                    Click here to re-send the verification email.
                                </Link>
                            </p>
                            <p v-if="status === 'verification-link-sent'" class="mt-2 text-sm font-medium text-green-600">
                                A new verification link has been sent to your email address.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Phone Number -->
                <div>
                    <InputLabel for="mobile_number" value="Phone Number" />
                    <div class="mt-1 relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <PhoneIcon class="h-5 w-5 text-gray-400" />
                        </div>
                        <TextInput
                            id="mobile_number"
                            v-model="form.mobile_number"
                            type="tel"
                            class="pl-10 block w-full"
                            placeholder="+254 712 345 678"
                        />
                    </div>
                    <InputError :message="form.errors.mobile_number" class="mt-2" />
                </div>
            </div>

            <!-- Submit -->
            <div class="mt-6 flex items-center justify-between border-t border-gray-200 pt-4">
                <p class="text-xs text-gray-500">
                    Account type: <span class="font-medium text-gray-700">{{ roleLabel }}</span>
                </p>
                <div class="flex items-center gap-4">
                    <Transition
                        enter-active-class="transition ease-in-out"
                        enter-from-class="opacity-0"
                        leave-active-class="transition ease-in-out"
                        leave-to-class="opacity-0"
                    >
                        <p v-if="form.recentlySuccessful" class="text-sm text-green-600">
                            Saved.
                        </p>
                    </Transition>
                    <PrimaryButton :disabled="form.processing">
                        <span v-if="form.processing">Saving...</span>
                        <span v-else>Save Changes</span>
                    </PrimaryButton>
                </div>
            </div>
        </form>
    </div>
</template>
