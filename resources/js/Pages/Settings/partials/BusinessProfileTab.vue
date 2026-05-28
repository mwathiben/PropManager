<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import type { LandlordProfile } from '@/types';
import { useI18n } from '@/composables/useI18n';

const props = withDefaults(defineProps<{
    landlordProfile?: LandlordProfile | null;
}>(), {
    landlordProfile: null,
});

const { t } = useI18n();

const form = useForm({
    company_name: props.landlordProfile?.company_name || '',
    business_registration_number: props.landlordProfile?.business_registration_number || '',
    tax_id: props.landlordProfile?.tax_id || '',
    address: props.landlordProfile?.address || '',
    city: props.landlordProfile?.city || '',
    country: props.landlordProfile?.country || 'Kenya',
    website: props.landlordProfile?.website || '',
});

const submit = () => {
    form.post(route('settings.business.update'), {
        preserveScroll: true,
    });
};
</script>

<template>
    <div class="space-y-6">
        <!-- Section Header -->
        <div>
            <h3 class="text-lg font-semibold text-gray-900">{{ t('settings_business_profile_tab.section_title') }}</h3>
            <p class="mt-1 text-sm text-gray-600">
                {{ t('settings_business_profile_tab.section_subtitle') }}
            </p>
        </div>

        <form @submit.prevent="submit" class="space-y-6">
            <!-- Company Information -->
            <div class="bg-gray-50 rounded-xl p-6 space-y-4">
                <h4 class="text-sm font-medium text-gray-700 uppercase tracking-wider">{{ t('settings_business_profile_tab.group_company_information') }}</h4>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <InputLabel for="company_name" :value="t('settings_business_profile_tab.company_name_label')" />
                        <TextInput
                            id="company_name"
                            v-model="form.company_name"
                            type="text"
                            class="mt-1 block w-full"
                            :placeholder="t('settings_business_profile_tab.company_name_placeholder')"
                        />
                        <InputError :message="form.errors.company_name" class="mt-2" />
                    </div>

                    <div>
                        <InputLabel for="business_registration_number" :value="t('settings_business_profile_tab.business_registration_number_label')" />
                        <TextInput
                            id="business_registration_number"
                            v-model="form.business_registration_number"
                            type="text"
                            class="mt-1 block w-full"
                            :placeholder="t('settings_business_profile_tab.business_registration_number_placeholder')"
                        />
                        <InputError :message="form.errors.business_registration_number" class="mt-2" />
                    </div>

                    <div>
                        <InputLabel for="tax_id" :value="t('settings_business_profile_tab.tax_id_label')" />
                        <TextInput
                            id="tax_id"
                            v-model="form.tax_id"
                            type="text"
                            class="mt-1 block w-full"
                            :placeholder="t('settings_business_profile_tab.tax_id_placeholder')"
                        />
                        <InputError :message="form.errors.tax_id" class="mt-2" />
                    </div>

                    <div>
                        <InputLabel for="website" :value="t('settings_business_profile_tab.website_label')" />
                        <TextInput
                            id="website"
                            v-model="form.website"
                            type="url"
                            class="mt-1 block w-full"
                            :placeholder="t('settings_business_profile_tab.website_placeholder')"
                        />
                        <InputError :message="form.errors.website" class="mt-2" />
                    </div>
                </div>
            </div>

            <!-- Address Information -->
            <div class="bg-gray-50 rounded-xl p-6 space-y-4">
                <h4 class="text-sm font-medium text-gray-700 uppercase tracking-wider">{{ t('settings_business_profile_tab.group_address') }}</h4>

                <div class="space-y-4">
                    <div>
                        <InputLabel for="address" :value="t('settings_business_profile_tab.street_address_label')" />
                        <textarea
                            id="address"
                            v-model="form.address"
                            rows="2"
                            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                            :placeholder="t('settings_business_profile_tab.street_address_placeholder')"
                        ></textarea>
                        <InputError :message="form.errors.address" class="mt-2" />
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <InputLabel for="city" :value="t('settings_business_profile_tab.city_label')" />
                            <TextInput
                                id="city"
                                v-model="form.city"
                                type="text"
                                class="mt-1 block w-full"
                                :placeholder="t('settings_business_profile_tab.city_placeholder')"
                            />
                            <InputError :message="form.errors.city" class="mt-2" />
                        </div>

                        <div>
                            <InputLabel for="country" :value="t('settings_business_profile_tab.country_label')" />
                            <TextInput
                                id="country"
                                v-model="form.country"
                                type="text"
                                class="mt-1 block w-full"
                                :placeholder="t('settings_business_profile_tab.country_placeholder')"
                            />
                            <InputError :message="form.errors.country" class="mt-2" />
                        </div>
                    </div>
                </div>
            </div>

            <!-- Submit Button -->
            <div class="flex justify-end">
                <PrimaryButton
                    :disabled="form.processing"
                    :class="{ 'opacity-50': form.processing }"
                >
                    {{ form.processing ? t('settings_business_profile_tab.saving_button') : t('settings_business_profile_tab.save_button') }}
                </PrimaryButton>
            </div>
        </form>
    </div>
</template>
