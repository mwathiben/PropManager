<script setup>
import { ref, computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import {
    UserCircleIcon,
    PhoneIcon,
    IdentificationIcon,
    UserGroupIcon,
    CameraIcon,
    CheckCircleIcon,
    ExclamationCircleIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps({
    user: Object,
});

const form = useForm({
    mobile_number: props.user.mobile_number || '',
    national_id: props.user.national_id || '',
    emergency_contact_name: props.user.emergency_contact_name || '',
    emergency_contact_phone: props.user.emergency_contact_phone || '',
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

    // Validate file size (2MB max)
    if (file.size > 2 * 1024 * 1024) {
        form.errors.profile_photo = 'Photo must not exceed 2MB';
        return;
    }

    // Validate file type
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

const completionStatus = computed(() => {
    const fields = [
        { name: 'Phone Number', complete: !!form.mobile_number },
        { name: 'National ID', complete: !!form.national_id },
        { name: 'Emergency Contact', complete: !!form.emergency_contact_name && !!form.emergency_contact_phone },
        { name: 'Profile Photo', complete: !!photoPreview.value },
    ];

    const completed = fields.filter(f => f.complete).length;
    return {
        fields,
        completed,
        total: fields.length,
        percentage: Math.round((completed / fields.length) * 100),
    };
});

const submit = () => {
    form.post(route('tenant.kyc.update'), {
        forceFormData: true,
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Complete Your Profile" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-3">
                <div class="p-2 bg-indigo-100 rounded-lg">
                    <UserCircleIcon class="w-6 h-6 text-indigo-600" />
                </div>
                <div>
                    <h1 class="text-lg font-semibold text-gray-900">Complete Your Profile</h1>
                    <p class="text-sm text-gray-500">Please provide the required information to continue</p>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
                <!-- Progress Card -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-sm font-medium text-gray-700">Profile Completion</h2>
                        <span class="text-sm font-semibold text-indigo-600">{{ completionStatus.percentage }}%</span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-2 mb-4">
                        <div
                            class="bg-indigo-600 h-2 rounded-full transition-all duration-300"
                            :style="{ width: completionStatus.percentage + '%' }"
                        ></div>
                    </div>
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                        <div
                            v-for="field in completionStatus.fields"
                            :key="field.name"
                            class="flex items-center gap-2 text-sm"
                        >
                            <CheckCircleIcon
                                v-if="field.complete"
                                class="w-5 h-5 text-green-500 flex-shrink-0"
                            />
                            <ExclamationCircleIcon
                                v-else
                                class="w-5 h-5 text-gray-300 flex-shrink-0"
                            />
                            <span :class="field.complete ? 'text-gray-700' : 'text-gray-400'">
                                {{ field.name }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Form Card -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                    <form @submit.prevent="submit" class="p-6 space-y-6">
                        <!-- Profile Photo -->
                        <div>
                            <InputLabel value="Profile Photo" class="mb-2" />
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
                                    required
                                />
                            </div>
                            <InputError :message="form.errors.mobile_number" class="mt-2" />
                        </div>

                        <!-- National ID -->
                        <div>
                            <InputLabel for="national_id" value="National ID / Passport Number" />
                            <div class="mt-1 relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <IdentificationIcon class="h-5 w-5 text-gray-400" />
                                </div>
                                <TextInput
                                    id="national_id"
                                    v-model="form.national_id"
                                    type="text"
                                    class="pl-10 block w-full"
                                    placeholder="Enter your ID number"
                                    required
                                />
                            </div>
                            <InputError :message="form.errors.national_id" class="mt-2" />
                        </div>

                        <!-- Emergency Contact Section -->
                        <div class="border-t border-gray-200 pt-6">
                            <div class="flex items-center gap-2 mb-4">
                                <UserGroupIcon class="w-5 h-5 text-gray-500" />
                                <h3 class="text-sm font-medium text-gray-900">Emergency Contact</h3>
                            </div>
                            <p class="text-sm text-gray-500 mb-4">
                                Please provide someone we can contact in case of an emergency.
                            </p>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <InputLabel for="emergency_contact_name" value="Contact Name" />
                                    <TextInput
                                        id="emergency_contact_name"
                                        v-model="form.emergency_contact_name"
                                        type="text"
                                        class="mt-1 block w-full"
                                        placeholder="Full name"
                                        required
                                    />
                                    <InputError :message="form.errors.emergency_contact_name" class="mt-2" />
                                </div>
                                <div>
                                    <InputLabel for="emergency_contact_phone" value="Contact Phone" />
                                    <TextInput
                                        id="emergency_contact_phone"
                                        v-model="form.emergency_contact_phone"
                                        type="tel"
                                        class="mt-1 block w-full"
                                        placeholder="+254 712 345 678"
                                        required
                                    />
                                    <InputError :message="form.errors.emergency_contact_phone" class="mt-2" />
                                </div>
                            </div>
                        </div>

                        <!-- Submit Button -->
                        <div class="border-t border-gray-200 pt-6">
                            <div class="flex items-center justify-between">
                                <p class="text-sm text-gray-500">
                                    All fields are required to continue.
                                </p>
                                <PrimaryButton
                                    :disabled="form.processing || completionStatus.percentage < 100"
                                    :class="{ 'opacity-50 cursor-not-allowed': completionStatus.percentage < 100 }"
                                >
                                    <span v-if="form.processing">Saving...</span>
                                    <span v-else>Complete Profile</span>
                                </PrimaryButton>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Info Card -->
                <div class="mt-6 bg-blue-50 border border-blue-200 rounded-xl p-4">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-blue-400" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-sm font-medium text-blue-800">Why we need this information</h3>
                            <p class="mt-1 text-sm text-blue-700">
                                Your profile information helps us verify your identity and contact you or your emergency contact if needed.
                                This information is kept secure and confidential.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
