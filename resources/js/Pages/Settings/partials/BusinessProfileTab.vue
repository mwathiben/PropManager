<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import type { LandlordProfile } from '@/types';

const props = withDefaults(defineProps<{
    landlordProfile?: LandlordProfile | null;
}>(), {
    landlordProfile: null,
});

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
            <h3 class="text-lg font-semibold text-gray-900">Business Profile</h3>
            <p class="mt-1 text-sm text-gray-600">
                Your business information appears on invoices and receipts sent to tenants.
            </p>
        </div>

        <form @submit.prevent="submit" class="space-y-6">
            <!-- Company Information -->
            <div class="bg-gray-50 rounded-xl p-6 space-y-4">
                <h4 class="text-sm font-medium text-gray-700 uppercase tracking-wider">Company Information</h4>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <InputLabel for="company_name" value="Company / Business Name" />
                        <TextInput
                            id="company_name"
                            v-model="form.company_name"
                            type="text"
                            class="mt-1 block w-full"
                            placeholder="e.g., ABC Properties Ltd"
                        />
                        <InputError :message="form.errors.company_name" class="mt-2" />
                    </div>

                    <div>
                        <InputLabel for="business_registration_number" value="Business Registration Number" />
                        <TextInput
                            id="business_registration_number"
                            v-model="form.business_registration_number"
                            type="text"
                            class="mt-1 block w-full"
                            placeholder="e.g., PVT-123456"
                        />
                        <InputError :message="form.errors.business_registration_number" class="mt-2" />
                    </div>

                    <div>
                        <InputLabel for="tax_id" value="Tax ID / KRA PIN" />
                        <TextInput
                            id="tax_id"
                            v-model="form.tax_id"
                            type="text"
                            class="mt-1 block w-full"
                            placeholder="e.g., A123456789B"
                        />
                        <InputError :message="form.errors.tax_id" class="mt-2" />
                    </div>

                    <div>
                        <InputLabel for="website" value="Website" />
                        <TextInput
                            id="website"
                            v-model="form.website"
                            type="url"
                            class="mt-1 block w-full"
                            placeholder="https://www.example.com"
                        />
                        <InputError :message="form.errors.website" class="mt-2" />
                    </div>
                </div>
            </div>

            <!-- Address Information -->
            <div class="bg-gray-50 rounded-xl p-6 space-y-4">
                <h4 class="text-sm font-medium text-gray-700 uppercase tracking-wider">Address</h4>

                <div class="space-y-4">
                    <div>
                        <InputLabel for="address" value="Street Address" />
                        <textarea
                            id="address"
                            v-model="form.address"
                            rows="2"
                            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                            placeholder="e.g., 123 Kimathi Street, Suite 4B"
                        ></textarea>
                        <InputError :message="form.errors.address" class="mt-2" />
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <InputLabel for="city" value="City" />
                            <TextInput
                                id="city"
                                v-model="form.city"
                                type="text"
                                class="mt-1 block w-full"
                                placeholder="e.g., Nairobi"
                            />
                            <InputError :message="form.errors.city" class="mt-2" />
                        </div>

                        <div>
                            <InputLabel for="country" value="Country" />
                            <TextInput
                                id="country"
                                v-model="form.country"
                                type="text"
                                class="mt-1 block w-full"
                                placeholder="e.g., Kenya"
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
                    {{ form.processing ? 'Saving...' : 'Save Business Profile' }}
                </PrimaryButton>
            </div>
        </form>
    </div>
</template>
