<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { useI18n } from '@/composables/useI18n';
import {
    BuildingOfficeIcon,
    DocumentTextIcon,
    MapPinIcon,
    GlobeAltIcon,
} from '@heroicons/vue/24/outline';
import type { ProfileUser, LandlordProfile } from '@/types';

const { t } = useI18n();

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
                    <h3 class="text-sm font-medium text-emerald-800">{{ t('profile_business.banner.title') }}</h3>
                    <p class="mt-1 text-sm text-emerald-700">
                        {{ t('profile_business.banner.body') }}
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
                        <h3 class="text-sm font-medium text-gray-900">{{ t('profile_business.company.title') }}</h3>
                        <p class="text-xs text-gray-500">{{ t('profile_business.company.subtitle') }}</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <!-- Company Name -->
                    <div class="sm:col-span-2">
                        <InputLabel for="company_name" :value="t('profile_business.labels.company_name')" />
                        <div class="mt-1 relative">
                            <div class="absolute inset-y-0 start-0 ps-3 flex items-center pointer-events-none">
                                <BuildingOfficeIcon class="h-5 w-5 text-gray-400" />
                            </div>
                            <TextInput
                                id="company_name"
                                v-model="form.business_profile.company_name"
                                type="text"
                                class="ps-10 block w-full"
                                :placeholder="t('profile_business.placeholders.company_name')"
                            />
                        </div>
                        <InputError :message="form.errors['business_profile.company_name']" class="mt-2" />
                    </div>

                    <!-- Business Registration Number -->
                    <div>
                        <InputLabel for="business_registration_number" :value="t('profile_business.labels.registration_number')" />
                        <div class="mt-1 relative">
                            <div class="absolute inset-y-0 start-0 ps-3 flex items-center pointer-events-none">
                                <DocumentTextIcon class="h-5 w-5 text-gray-400" />
                            </div>
                            <TextInput
                                id="business_registration_number"
                                v-model="form.business_profile.business_registration_number"
                                type="text"
                                class="ps-10 block w-full"
                                :placeholder="t('profile_business.placeholders.registration_number')"
                            />
                        </div>
                        <InputError :message="form.errors['business_profile.business_registration_number']" class="mt-2" />
                    </div>

                    <!-- Tax ID -->
                    <div>
                        <InputLabel for="tax_id" :value="t('profile_business.labels.tax_id')" />
                        <div class="mt-1 relative">
                            <div class="absolute inset-y-0 start-0 ps-3 flex items-center pointer-events-none">
                                <DocumentTextIcon class="h-5 w-5 text-gray-400" />
                            </div>
                            <TextInput
                                id="tax_id"
                                v-model="form.business_profile.tax_id"
                                type="text"
                                class="ps-10 block w-full"
                                :placeholder="t('profile_business.placeholders.tax_id')"
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
                        <h3 class="text-sm font-medium text-gray-900">{{ t('profile_business.address.title') }}</h3>
                        <p class="text-xs text-gray-500">{{ t('profile_business.address.subtitle') }}</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <!-- Address -->
                    <div class="sm:col-span-2">
                        <InputLabel for="address" :value="t('profile_business.labels.street_address')" />
                        <div class="mt-1 relative">
                            <div class="absolute inset-y-0 start-0 ps-3 flex items-center pointer-events-none">
                                <MapPinIcon class="h-5 w-5 text-gray-400" />
                            </div>
                            <TextInput
                                id="address"
                                v-model="form.business_profile.address"
                                type="text"
                                class="ps-10 block w-full"
                                :placeholder="t('profile_business.placeholders.street_address')"
                            />
                        </div>
                        <InputError :message="form.errors['business_profile.address']" class="mt-2" />
                    </div>

                    <!-- City -->
                    <div>
                        <InputLabel for="city" :value="t('profile_business.labels.city')" />
                        <TextInput
                            id="city"
                            v-model="form.business_profile.city"
                            type="text"
                            class="mt-1 block w-full"
                            :placeholder="t('profile_business.placeholders.city')"
                        />
                        <InputError :message="form.errors['business_profile.city']" class="mt-2" />
                    </div>

                    <!-- Country -->
                    <div>
                        <InputLabel for="country" :value="t('profile_business.labels.country')" />
                        <TextInput
                            id="country"
                            v-model="form.business_profile.country"
                            type="text"
                            class="mt-1 block w-full"
                            :placeholder="t('profile_business.placeholders.country')"
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
                        <h3 class="text-sm font-medium text-gray-900">{{ t('profile_business.online.title') }}</h3>
                        <p class="text-xs text-gray-500">{{ t('profile_business.online.subtitle') }}</p>
                    </div>
                </div>

                <div>
                    <InputLabel for="website" :value="t('profile_business.labels.website')" />
                    <div class="mt-1 relative">
                        <div class="absolute inset-y-0 start-0 ps-3 flex items-center pointer-events-none">
                            <GlobeAltIcon class="h-5 w-5 text-gray-400" />
                        </div>
                        <TextInput
                            id="website"
                            v-model="form.business_profile.website"
                            type="url"
                            class="ps-10 block w-full"
                            :placeholder="t('profile_business.placeholders.website')"
                        />
                    </div>
                    <InputError :message="form.errors['business_profile.website']" class="mt-2" />
                </div>
            </div>

            <!-- Submit -->
            <div class="flex items-center justify-end">
                <div class="flex items-center gap-4">
                    <!-- i18n-ignore -->
                    <Transition enter-active-class="transition ease-in-out" enter-from-class="opacity-0" leave-active-class="transition ease-in-out" leave-to-class="opacity-0">
                        <p v-if="form.recentlySuccessful" class="text-sm text-green-600">
                            {{ t('profile_business.saved') }}
                        </p>
                    </Transition>
                    <PrimaryButton :disabled="form.processing">
                        <span v-if="form.processing">{{ t('profile_business.saving') }}</span>
                        <span v-else>{{ t('profile_business.save') }}</span>
                    </PrimaryButton>
                </div>
            </div>
        </form>
    </div>
</template>
