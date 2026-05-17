<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import {
    BuildingOfficeIcon,
    DocumentTextIcon,
    MapPinIcon,
    GlobeAltIcon,
} from '@heroicons/vue/24/outline';
import type { ProfileUser, LandlordProfile } from '@/types';

const props = defineProps<{
    user: ProfileUser;
    landlordProfile?: LandlordProfile | null;
}>();

const form = useForm({
    name: props.user.name,
    email: props.user.email,
    mobile_number: props.user.mobile_number || '',
    business_profile: {
        company_name: props.landlordProfile?.company_name || '',
        business_registration_number: props.landlordProfile?.business_registration_number || '',
        tax_id: props.landlordProfile?.tax_id || '',
        address: props.landlordProfile?.address || '',
        city: props.landlordProfile?.city || '',
        country: props.landlordProfile?.country || '',
        website: props.landlordProfile?.website || '',
    },
});

const submit = () => {
    form.patch(route('profile.update'), {
        preserveScroll: true,
    });
};
</script>

<template>
    <div class="space-y-6">
        <!-- Business Info Banner -->
        <div class="bg-emerald-50 border border-emerald-200 rounded-xl p-4">
            <div class="flex">
                <div class="shrink-0">
                    <BuildingOfficeIcon class="h-5 w-5 text-emerald-400" />
                </div>
                <div class="ms-3">
                    <h3 class="text-sm font-medium text-emerald-800">Business Profile</h3>
                    <p class="mt-1 text-sm text-emerald-700">
                        Your business information is displayed on invoices and tenant communications.
                    </p>
                </div>
            </div>
        </div>

        <!-- Business Profile Form -->
        <form @submit.prevent="submit" class="space-y-6">
            <!-- Company Information -->
            <div class="bg-white rounded-xl border border-gray-200 p-6">
                <div class="flex items-center gap-3 mb-4">
                    <div class="p-2 bg-gray-100 rounded-lg">
                        <BuildingOfficeIcon class="w-5 h-5 text-gray-600" />
                    </div>
                    <div>
                        <h3 class="text-sm font-medium text-gray-900">Company Information</h3>
                        <p class="text-xs text-gray-500">Your business name and registration details</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <!-- Company Name -->
                    <div class="sm:col-span-2">
                        <InputLabel for="company_name" value="Company Name" />
                        <div class="mt-1 relative">
                            <div class="absolute inset-y-0 start-0 ps-3 flex items-center pointer-events-none">
                                <BuildingOfficeIcon class="h-5 w-5 text-gray-400" />
                            </div>
                            <TextInput
                                id="company_name"
                                v-model="form.business_profile.company_name"
                                type="text"
                                class="ps-10 block w-full"
                                placeholder="Your company or business name"
                            />
                        </div>
                        <InputError :message="form.errors['business_profile.company_name']" class="mt-2" />
                    </div>

                    <!-- Business Registration Number -->
                    <div>
                        <InputLabel for="business_registration_number" value="Registration Number" />
                        <div class="mt-1 relative">
                            <div class="absolute inset-y-0 start-0 ps-3 flex items-center pointer-events-none">
                                <DocumentTextIcon class="h-5 w-5 text-gray-400" />
                            </div>
                            <TextInput
                                id="business_registration_number"
                                v-model="form.business_profile.business_registration_number"
                                type="text"
                                class="ps-10 block w-full"
                                placeholder="CR-123456"
                            />
                        </div>
                        <InputError :message="form.errors['business_profile.business_registration_number']" class="mt-2" />
                    </div>

                    <!-- Tax ID -->
                    <div>
                        <InputLabel for="tax_id" value="Tax ID / KRA PIN" />
                        <div class="mt-1 relative">
                            <div class="absolute inset-y-0 start-0 ps-3 flex items-center pointer-events-none">
                                <DocumentTextIcon class="h-5 w-5 text-gray-400" />
                            </div>
                            <TextInput
                                id="tax_id"
                                v-model="form.business_profile.tax_id"
                                type="text"
                                class="ps-10 block w-full"
                                placeholder="A123456789X"
                            />
                        </div>
                        <InputError :message="form.errors['business_profile.tax_id']" class="mt-2" />
                    </div>
                </div>
            </div>

            <!-- Business Address -->
            <div class="bg-white rounded-xl border border-gray-200 p-6">
                <div class="flex items-center gap-3 mb-4">
                    <div class="p-2 bg-gray-100 rounded-lg">
                        <MapPinIcon class="w-5 h-5 text-gray-600" />
                    </div>
                    <div>
                        <h3 class="text-sm font-medium text-gray-900">Business Address</h3>
                        <p class="text-xs text-gray-500">Where your business is located</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <!-- Address -->
                    <div class="sm:col-span-2">
                        <InputLabel for="address" value="Street Address" />
                        <div class="mt-1 relative">
                            <div class="absolute inset-y-0 start-0 ps-3 flex items-center pointer-events-none">
                                <MapPinIcon class="h-5 w-5 text-gray-400" />
                            </div>
                            <TextInput
                                id="address"
                                v-model="form.business_profile.address"
                                type="text"
                                class="ps-10 block w-full"
                                placeholder="123 Business Street"
                            />
                        </div>
                        <InputError :message="form.errors['business_profile.address']" class="mt-2" />
                    </div>

                    <!-- City -->
                    <div>
                        <InputLabel for="city" value="City" />
                        <TextInput
                            id="city"
                            v-model="form.business_profile.city"
                            type="text"
                            class="mt-1 block w-full"
                            placeholder="Nairobi"
                        />
                        <InputError :message="form.errors['business_profile.city']" class="mt-2" />
                    </div>

                    <!-- Country -->
                    <div>
                        <InputLabel for="country" value="Country" />
                        <TextInput
                            id="country"
                            v-model="form.business_profile.country"
                            type="text"
                            class="mt-1 block w-full"
                            placeholder="Kenya"
                        />
                        <InputError :message="form.errors['business_profile.country']" class="mt-2" />
                    </div>
                </div>
            </div>

            <!-- Online Presence -->
            <div class="bg-white rounded-xl border border-gray-200 p-6">
                <div class="flex items-center gap-3 mb-4">
                    <div class="p-2 bg-gray-100 rounded-lg">
                        <GlobeAltIcon class="w-5 h-5 text-gray-600" />
                    </div>
                    <div>
                        <h3 class="text-sm font-medium text-gray-900">Online Presence</h3>
                        <p class="text-xs text-gray-500">Your company website (optional)</p>
                    </div>
                </div>

                <div>
                    <InputLabel for="website" value="Website" />
                    <div class="mt-1 relative">
                        <div class="absolute inset-y-0 start-0 ps-3 flex items-center pointer-events-none">
                            <GlobeAltIcon class="h-5 w-5 text-gray-400" />
                        </div>
                        <TextInput
                            id="website"
                            v-model="form.business_profile.website"
                            type="url"
                            class="ps-10 block w-full"
                            placeholder="https://yourcompany.com"
                        />
                    </div>
                    <InputError :message="form.errors['business_profile.website']" class="mt-2" />
                </div>
            </div>

            <!-- Submit -->
            <div class="flex items-center justify-end">
                <div class="flex items-center gap-4">
                    <Transition
                        enter-active-class="transition ease-in-out"
                        enter-from-class="opacity-0"
                        leave-active-class="transition ease-in-out"
                        leave-to-class="opacity-0"
                    >
                        <p v-if="form.recentlySuccessful" class="text-sm text-green-600">
                            Business profile saved.
                        </p>
                    </Transition>
                    <PrimaryButton :disabled="form.processing">
                        <span v-if="form.processing">Saving...</span>
                        <span v-else>Save Business Profile</span>
                    </PrimaryButton>
                </div>
            </div>
        </form>
    </div>
</template>
