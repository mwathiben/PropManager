<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { __ } from '@/lang';

interface VendorPayload {
    id: number;
    name: string;
    contact_person: string | null;
    phone: string | null;
    address: string | null;
    notes: string | null;
}

const props = defineProps<{ vendor: VendorPayload }>();

const form = useForm({
    contact_person: props.vendor.contact_person ?? '',
    phone: props.vendor.phone ?? '',
    address: props.vendor.address ?? '',
    notes: props.vendor.notes ?? '',
});

function submit(): void {
    form.patch(window.location.pathname);
}
</script>

<template>
    <Head :title="__('maintenance.vendor_onboarding.form.title')" />

    <div class="min-h-screen bg-gradient-to-br from-indigo-50 via-white to-emerald-50 px-4 py-10">
        <div class="mx-auto max-w-xl rounded-2xl bg-white p-8 shadow-xl ring-1 ring-gray-100">
            <header class="mb-6 text-center">
                <div class="mx-auto h-12 w-12 rounded-full bg-indigo-100 text-indigo-700 grid place-items-center font-bold">
                    {{ props.vendor.name.charAt(0) }}
                </div>
                <h1 class="mt-3 text-xl font-semibold text-gray-900">
                    {{ __('maintenance.vendor_onboarding.form.title') }}
                </h1>
                <p class="mt-1 text-sm text-gray-600">
                    {{ __('maintenance.vendor_onboarding.form.intro') }}
                </p>
                <p class="mt-2 text-xs text-gray-500">{{ props.vendor.name }}</p>
            </header>

            <form class="space-y-4" @submit.prevent="submit">
                <div>
                    <label class="block text-xs font-semibold text-gray-700">
                        {{ __('maintenance.vendor_onboarding.form.contact_person') }}
                    </label>
                    <input
                        v-model="form.contact_person"
                        type="text"
                        maxlength="100"
                        class="mt-1 w-full rounded border-gray-300 text-sm"
                    >
                    <p v-if="form.errors.contact_person" class="mt-1 text-xs text-rose-600">{{ form.errors.contact_person }}</p>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-700">
                        {{ __('maintenance.vendor_onboarding.form.phone') }}
                    </label>
                    <input
                        v-model="form.phone"
                        type="tel"
                        maxlength="30"
                        class="mt-1 w-full rounded border-gray-300 text-sm"
                    >
                    <p v-if="form.errors.phone" class="mt-1 text-xs text-rose-600">{{ form.errors.phone }}</p>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-700">
                        {{ __('maintenance.vendor_onboarding.form.address') }}
                    </label>
                    <input
                        v-model="form.address"
                        type="text"
                        maxlength="255"
                        class="mt-1 w-full rounded border-gray-300 text-sm"
                    >
                    <p v-if="form.errors.address" class="mt-1 text-xs text-rose-600">{{ form.errors.address }}</p>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-700">
                        {{ __('maintenance.vendor_onboarding.form.notes') }}
                    </label>
                    <textarea
                        v-model="form.notes"
                        rows="3"
                        maxlength="1000"
                        class="mt-1 w-full rounded border-gray-300 text-sm"
                    ></textarea>
                    <p v-if="form.errors.notes" class="mt-1 text-xs text-rose-600">{{ form.errors.notes }}</p>
                </div>

                <button
                    type="submit"
                    :disabled="form.processing"
                    class="w-full rounded bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-60"
                >
                    {{ __('maintenance.vendor_onboarding.form.submit') }}
                </button>
            </form>
        </div>
    </div>
</template>
