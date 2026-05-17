<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import {
    LockClosedIcon,
    ShieldCheckIcon,
    KeyIcon,
} from '@heroicons/vue/24/outline';

const passwordInput = ref(null);
const currentPasswordInput = ref(null);

const form = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const updatePassword = () => {
    form.put(route('password.update'), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
        onError: () => {
            if (form.errors.password) {
                form.reset('password', 'password_confirmation');
                passwordInput.value.focus();
            }
            if (form.errors.current_password) {
                form.reset('current_password');
                currentPasswordInput.value.focus();
            }
        },
    });
};
</script>

<template>
    <div class="space-y-6">
        <!-- Security Info Banner -->
        <div class="bg-indigo-50 border border-indigo-200 rounded-xl p-4">
            <div class="flex">
                <div class="shrink-0">
                    <ShieldCheckIcon class="h-5 w-5 text-indigo-400" />
                </div>
                <div class="ms-3">
                    <h3 class="text-sm font-medium text-indigo-800">Account Security</h3>
                    <p class="mt-1 text-sm text-indigo-700">
                        Keep your account secure by using a strong password and enabling two-factor authentication.
                    </p>
                </div>
            </div>
        </div>

        <!-- Password Update Form -->
        <form @submit.prevent="updatePassword" class="bg-white rounded-xl border border-gray-200 p-6">
            <div class="flex items-center gap-3 mb-4">
                <div class="p-2 bg-gray-100 rounded-lg">
                    <KeyIcon class="w-5 h-5 text-gray-600" />
                </div>
                <div>
                    <h3 class="text-sm font-medium text-gray-900">Update Password</h3>
                    <p class="text-xs text-gray-500">Ensure your account is using a strong, unique password</p>
                </div>
            </div>

            <div class="space-y-4">
                <!-- Current Password -->
                <div>
                    <InputLabel for="current_password" value="Current Password" />
                    <div class="mt-1 relative">
                        <div class="absolute inset-y-0 start-0 ps-3 flex items-center pointer-events-none">
                            <LockClosedIcon class="h-5 w-5 text-gray-400" />
                        </div>
                        <TextInput
                            id="current_password"
                            ref="currentPasswordInput"
                            v-model="form.current_password"
                            type="password"
                            class="ps-10 block w-full"
                            autocomplete="current-password"
                            placeholder="Enter your current password"
                        />
                    </div>
                    <InputError :message="form.errors.current_password" class="mt-2" />
                </div>

                <!-- New Password -->
                <div>
                    <InputLabel for="password" value="New Password" />
                    <div class="mt-1 relative">
                        <div class="absolute inset-y-0 start-0 ps-3 flex items-center pointer-events-none">
                            <LockClosedIcon class="h-5 w-5 text-gray-400" />
                        </div>
                        <TextInput
                            id="password"
                            ref="passwordInput"
                            v-model="form.password"
                            type="password"
                            class="ps-10 block w-full"
                            autocomplete="new-password"
                            placeholder="Enter a new password"
                        />
                    </div>
                    <InputError :message="form.errors.password" class="mt-2" />
                </div>

                <!-- Confirm Password -->
                <div>
                    <InputLabel for="password_confirmation" value="Confirm New Password" />
                    <div class="mt-1 relative">
                        <div class="absolute inset-y-0 start-0 ps-3 flex items-center pointer-events-none">
                            <LockClosedIcon class="h-5 w-5 text-gray-400" />
                        </div>
                        <TextInput
                            id="password_confirmation"
                            v-model="form.password_confirmation"
                            type="password"
                            class="ps-10 block w-full"
                            autocomplete="new-password"
                            placeholder="Confirm your new password"
                        />
                    </div>
                    <InputError :message="form.errors.password_confirmation" class="mt-2" />
                </div>
            </div>

            <!-- Submit -->
            <div class="mt-6 flex items-center justify-end border-t border-gray-200 pt-4">
                <div class="flex items-center gap-4">
                    <Transition
                        enter-active-class="transition ease-in-out"
                        enter-from-class="opacity-0"
                        leave-active-class="transition ease-in-out"
                        leave-to-class="opacity-0"
                    >
                        <p v-if="form.recentlySuccessful" class="text-sm text-green-600">
                            Password updated.
                        </p>
                    </Transition>
                    <PrimaryButton :disabled="form.processing">
                        <span v-if="form.processing">Updating...</span>
                        <span v-else>Update Password</span>
                    </PrimaryButton>
                </div>
            </div>
        </form>

        <!-- Password Requirements -->
        <div class="bg-gray-50 rounded-xl border border-gray-200 p-4">
            <h4 class="text-xs font-medium text-gray-700 mb-2">Password Requirements</h4>
            <ul class="text-xs text-gray-600 space-y-1">
                <li class="flex items-center gap-2">
                    <span class="w-1 h-1 bg-gray-400 rounded-full"></span>
                    At least 8 characters long
                </li>
                <li class="flex items-center gap-2">
                    <span class="w-1 h-1 bg-gray-400 rounded-full"></span>
                    Include uppercase and lowercase letters
                </li>
                <li class="flex items-center gap-2">
                    <span class="w-1 h-1 bg-gray-400 rounded-full"></span>
                    Include at least one number
                </li>
                <li class="flex items-center gap-2">
                    <span class="w-1 h-1 bg-gray-400 rounded-full"></span>
                    Include at least one special character
                </li>
            </ul>
        </div>
    </div>
</template>
