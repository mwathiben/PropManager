<script setup lang="ts">
import { Head, useForm, router, usePage } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import TenantSteps from './TenantSteps.vue';
import CaretakerSteps from './CaretakerSteps.vue';
import { useFormatters, useCurrency } from '@/composables';
const { formatMoney: formatCurrency, todayAsISODate } = useFormatters();
const { currencyCode, currencySymbol } = useCurrency();
import CheckCircleIcon from '@heroicons/vue/24/outline/CheckCircleIcon';
import BuildingOffice2Icon from '@heroicons/vue/24/outline/BuildingOffice2Icon';
import HomeModernIcon from '@heroicons/vue/24/outline/HomeModernIcon';
import BuildingStorefrontIcon from '@heroicons/vue/24/outline/BuildingStorefrontIcon';
import HomeIcon from '@heroicons/vue/24/outline/HomeIcon';
import PlusIcon from '@heroicons/vue/24/outline/PlusIcon';
import TrashIcon from '@heroicons/vue/24/outline/TrashIcon';
import UserIcon from '@heroicons/vue/24/outline/UserIcon';
import CurrencyDollarIcon from '@heroicons/vue/24/outline/CurrencyDollarIcon';
import UserGroupIcon from '@heroicons/vue/24/outline/UserGroupIcon';
import UserPlusIcon from '@heroicons/vue/24/outline/UserPlusIcon';
import RocketLaunchIcon from '@heroicons/vue/24/outline/RocketLaunchIcon';
import SparklesIcon from '@heroicons/vue/24/outline/SparklesIcon';
import ArrowRightIcon from '@heroicons/vue/24/outline/ArrowRightIcon';
import ArrowLeftIcon from '@heroicons/vue/24/outline/ArrowLeftIcon';
import ChevronRightIcon from '@heroicons/vue/24/outline/ChevronRightIcon';
import PhotoIcon from '@heroicons/vue/24/outline/PhotoIcon';
import PhoneIcon from '@heroicons/vue/24/outline/PhoneIcon';
import EnvelopeIcon from '@heroicons/vue/24/outline/EnvelopeIcon';
import MapPinIcon from '@heroicons/vue/24/outline/MapPinIcon';
import BuildingOfficeIcon from '@heroicons/vue/24/outline/BuildingOfficeIcon';
import BanknotesIcon from '@heroicons/vue/24/outline/BanknotesIcon';
import CreditCardIcon from '@heroicons/vue/24/outline/CreditCardIcon';
import DevicePhoneMobileIcon from '@heroicons/vue/24/outline/DevicePhoneMobileIcon';
import CheckIcon from '@heroicons/vue/24/outline/CheckIcon';
import XMarkIcon from '@heroicons/vue/24/outline/XMarkIcon';
import CalendarIcon from '@heroicons/vue/24/outline/CalendarIcon';
import ClockIcon from '@heroicons/vue/24/outline/ClockIcon';
import CheckCircleSolidIcon from '@heroicons/vue/24/solid/CheckCircleIcon';
import type {
    OnboardingProfile,
    OnboardingUser,
    OnboardingProperty,
    OnboardingPaymentConfig,
    OnboardingInvitation,
    OnboardingVacantUnit,
    OnboardingSummary,
    OnboardingStepData
} from '@/types';

const props = withDefaults(defineProps<{
    currentStep: number;
    totalSteps: number;
    completedSteps?: number[];
    stepData?: OnboardingStepData;
    stepName: string;
    isOptionalStep?: boolean;
    profile?: OnboardingProfile;
    user?: OnboardingUser;
    existingProperty?: OnboardingProperty;
    property?: OnboardingProperty;
    paymentConfig?: OnboardingPaymentConfig;
    existingInvitations?: OnboardingInvitation[];
    vacantUnits?: OnboardingVacantUnit[];
    summary?: OnboardingSummary;
}>(), {
    completedSteps: () => [],
    stepData: () => ({}),
    isOptionalStep: false,
    profile: undefined,
    user: undefined,
    existingProperty: undefined,
    property: undefined,
    paymentConfig: undefined,
    existingInvitations: () => [],
    vacantUnits: () => [],
    summary: undefined,
});

// Phase-47 WIZARD-VUE-1: top-level role dispatch. A tenant or caretaker
// reaching this page now renders the role-appropriate scaffold instead of
// landing on the landlord profile form (which 422s on submit because the
// validation expects landlord-shaped fields).
const page = usePage();
const role = computed(() => (page.props as { auth?: { user?: { role?: string } } }).auth?.user?.role ?? 'landlord');


// Step definitions with icons and descriptions
const steps = [
    { number: 1, name: 'welcome', label: 'Welcome', icon: SparklesIcon, description: 'Get started' },
    { number: 2, name: 'profile', label: 'Profile', icon: UserIcon, description: 'Your details' },
    { number: 3, name: 'property', label: 'Property', icon: BuildingOffice2Icon, description: 'Add property' },
    { number: 4, name: 'structure', label: 'Structure', icon: HomeModernIcon, description: 'Configure units' },
    { number: 5, name: 'financial', label: 'Financial', icon: CurrencyDollarIcon, description: 'Payment setup' },
    { number: 6, name: 'team', label: 'Team', icon: UserGroupIcon, description: 'Add caretakers', optional: true },
    { number: 7, name: 'first-tenant', label: 'First Tenant', icon: UserPlusIcon, description: 'Invite tenant', optional: true },
    { number: 8, name: 'complete', label: 'Complete', icon: RocketLaunchIcon, description: 'All done!' },
];

// Form for current step
const form = useForm({
    // Step 1: Welcome
    acknowledged: false,
    // Step 2: Profile
    name: props.user?.name || '',
    mobile_number: props.user?.mobile_number || '',
    company_name: props.profile?.company_name || '',
    business_registration_number: props.profile?.business_registration_number || '',
    address: props.profile?.address || '',
    city: props.profile?.city || '',
    country: props.profile?.country || 'Kenya',
    // Step 3: Property
    property_name: props.stepData?.property_name || props.existingProperty?.name || '',
    property_type: props.stepData?.property_type || props.existingProperty?.type || 'residential',
    property_address: props.stepData?.address || props.existingProperty?.address || '',
    // Step 4: Structure
    has_wings: props.stepData?.has_wings || false,
    floors: props.stepData?.floors || 5,
    units_per_floor: props.stepData?.units_per_floor || 4,
    wings: props.stepData?.wings || [],
    // Step 5: Financial
    default_rent: props.paymentConfig?.default_rent || props.stepData?.default_rent || 20000,
    water_billing_type: props.paymentConfig?.water_billing_type || props.stepData?.water_billing_type || 'consumption',
    flat_water_rate: props.paymentConfig?.flat_water_rate || props.stepData?.flat_water_rate || 500,
    water_unit_rate: props.paymentConfig?.water_unit_rate || props.stepData?.water_unit_rate || '',
    accepted_payment_methods: props.paymentConfig?.accepted_payment_methods || props.stepData?.accepted_payment_methods || ['cash', 'mobile_money'],
    bank_name: props.paymentConfig?.bank_name || '',
    bank_account_name: props.paymentConfig?.bank_account_name || '',
    bank_account_number: props.paymentConfig?.bank_account_number || '',
    mpesa_paybill: props.paymentConfig?.mpesa_paybill || '',
    // Step 6: Team
    invitations: [],
    // Step 7: First Tenant
    unit_id: '',
    tenant_email: '',
    tenant_name: '',
    tenant_phone: '',
    rent_amount: props.paymentConfig?.default_rent || 20000,
    deposit_amount: props.paymentConfig?.default_rent || 20000,
    start_date: todayAsISODate(),
});

// Initialize wings on load
if (form.has_wings && form.wings.length === 0) {
    addWing();
    addWing();
}

// Wing management
function addWing() {
    const letter = String.fromCharCode(65 + form.wings.length);
    form.wings.push({
        name: `Block ${letter}`,
        prefix: letter,
        floors: 5,
        units_per_floor: 4,
    });
}

function removeWing(index) {
    form.wings.splice(index, 1);
}

function updatePrefix(index) {
    const wing = form.wings[index];
    if (wing.name) {
        const match = wing.name.match(/^Block\s+(\w)/i);
        wing.prefix = match ? match[1].toUpperCase() : wing.name[0].toUpperCase();
    }
}

// Watch wings mode toggle
watch(() => form.has_wings, (hasWings) => {
    if (hasWings && form.wings.length === 0) {
        addWing();
        addWing();
    }
});

// Team invitation management
function addTeamInvitation() {
    form.invitations.push({
        email: '',
        property_id: null,
    });
}

function removeTeamInvitation(index) {
    form.invitations.splice(index, 1);
}

// Property type selection
function selectPropertyType(type) {
    form.property_type = type;
    if (type === 'estate') {
        form.floors = 1;
        form.units_per_floor = 10;
        form.has_wings = false;
    } else {
        form.floors = 5;
        form.units_per_floor = 4;
    }
}

// Computed values
const floorLabel = computed(() => form.property_type === 'estate' ? 'Number of Streets / Courts' : 'Number of Floors');
const unitLabel = computed(() => form.property_type === 'estate' ? 'Houses per Street' : 'Units per Floor');

const totalUnits = computed(() => {
    if (form.has_wings) {
        return form.wings.reduce((sum, wing) => sum + (wing.floors * wing.units_per_floor), 0);
    }
    return form.floors * form.units_per_floor;
});

const unitNamingPreview = computed(() => {
    if (form.has_wings && form.wings.length > 0) {
        return form.wings.map(wing => `${wing.prefix}101, ${wing.prefix}102...`).join(' | ');
    }
    return '101, 102, 201, 202...';
});

const progressPercentage = computed(() => {
    return Math.round((props.completedSteps.length / props.totalSteps) * 100);
});

const isStepComplete = (stepNumber) => {
    return props.completedSteps.includes(stepNumber);
};

const canAccessStep = (stepNumber) => {
    if (stepNumber === 1) return true;
    return props.completedSteps.includes(stepNumber - 1) || stepNumber <= props.currentStep;
};

// Payment method helpers
const paymentMethodOptions = [
    { id: 'cash', label: 'Cash', icon: BanknotesIcon, description: 'In-person payments' },
    { id: 'bank_transfer', label: 'Bank Transfer', icon: BuildingOfficeIcon, description: 'Direct deposits' },
    { id: 'mobile_money', label: 'M-Pesa', icon: DevicePhoneMobileIcon, description: 'Mobile money' },
    { id: 'paystack', label: 'Online (Paystack)', icon: CreditCardIcon, description: 'Cards & more' },
];

function togglePaymentMethod(methodId) {
    const index = form.accepted_payment_methods.indexOf(methodId);
    if (index > -1) {
        form.accepted_payment_methods.splice(index, 1);
    } else {
        form.accepted_payment_methods.push(methodId);
    }
}

// Submit current step
function submitStep() {
    form.post(route('onboarding.step.save', { step: props.currentStep }), {
        preserveScroll: true,
    });
}

// Skip optional step
function skipStep() {
    router.post(route('onboarding.step.skip', { step: props.currentStep }), {}, {
        preserveScroll: true,
    });
}

// Navigate to previous step
function goBack() {
    if (props.currentStep > 1) {
        router.get(route('onboarding.step', { step: props.currentStep - 1 }));
    }
}

// Navigate to specific step (only if allowed)
function goToStep(stepNumber) {
    if (canAccessStep(stepNumber)) {
        router.get(route('onboarding.step', { step: stepNumber }));
    }
}

// Complete onboarding
function completeOnboarding() {
    router.post(route('onboarding.complete'));
}

</script>

<template>
    <Head title="Setup Your Property" />

    <!-- Phase-47 WIZARD-VUE-1: tenant + caretaker scaffold dispatch. -->
    <TenantSteps v-if="role === 'tenant'" :current-step="currentStep" :completed-steps="completedSteps" />
    <CaretakerSteps v-else-if="role === 'caretaker'" :current-step="currentStep" :completed-steps="completedSteps" />

    <div v-else class="min-h-screen bg-gradient-to-br from-indigo-50 via-white to-purple-50">
        <!-- Progress Header -->
        <div class="bg-white border-b border-gray-200 sticky top-0 z-10">
            <div class="max-w-4xl mx-auto px-4 py-4">
                <!-- Progress Bar -->
                <div class="mb-4">
                    <div class="flex justify-between text-sm mb-2">
                        <span class="font-medium text-gray-700">Setting up your account</span>
                        <span class="text-gray-500">{{ progressPercentage }}% complete</span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-2">
                        <div
                            class="bg-gradient-to-r from-indigo-500 to-purple-500 h-2 rounded-full transition-all duration-500"
                            :style="{ width: progressPercentage + '%' }"
                        ></div>
                    </div>
                </div>

                <!-- Step Indicators (Desktop) -->
                <div class="hidden md:flex items-center justify-between">
                    <div
                        v-for="step in steps"
                        :key="step.number"
                        @click="goToStep(step.number)"
                        :class="[
                            'flex items-center gap-2 px-3 py-2 rounded-lg transition-all cursor-pointer',
                            currentStep === step.number ? 'bg-indigo-100 text-indigo-700' : '',
                            isStepComplete(step.number) ? 'text-green-600' : 'text-gray-400',
                            !canAccessStep(step.number) ? 'opacity-50 cursor-not-allowed' : 'hover:bg-gray-100',
                        ]"
                    >
                        <div
                            :class="[
                                'w-8 h-8 rounded-full flex items-center justify-center text-sm font-semibold transition-all',
                                currentStep === step.number ? 'bg-indigo-600 text-white' : '',
                                isStepComplete(step.number) && currentStep !== step.number ? 'bg-green-500 text-white' : '',
                                !isStepComplete(step.number) && currentStep !== step.number ? 'bg-gray-200 text-gray-500' : '',
                            ]"
                        >
                            <CheckIcon v-if="isStepComplete(step.number) && currentStep !== step.number" class="w-4 h-4" />
                            <span v-else>{{ step.number }}</span>
                        </div>
                        <div class="hidden lg:block">
                            <div class="text-xs font-medium">{{ step.label }}</div>
                            <div v-if="step.optional" class="text-xs text-gray-400">Optional</div>
                        </div>
                    </div>
                </div>

                <!-- Mobile Step Indicator -->
                <div class="md:hidden flex items-center justify-center gap-2">
                    <span class="text-sm font-medium text-gray-700">
                        Step {{ currentStep }} of {{ totalSteps }}: {{ steps[currentStep - 1]?.label }}
                    </span>
                    <span v-if="isOptionalStep" class="text-xs bg-gray-100 text-gray-500 px-2 py-0.5 rounded">Optional</span>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="max-w-3xl mx-auto px-4 py-8">
            <div class="bg-white rounded-2xl shadow-xl border border-gray-100 overflow-hidden">

                <!-- ==================== STEP 1: WELCOME ==================== -->
                <div v-if="currentStep === 1" class="p-8">
                    <div class="text-center mb-8">
                        <div class="w-20 h-20 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-full flex items-center justify-center mx-auto mb-4">
                            <SparklesIcon class="w-10 h-10 text-white" />
                        </div>
                        <h1 class="text-3xl font-bold text-gray-900 mb-2">Welcome to PropManager!</h1>
                        <p class="text-lg text-gray-600">Let's get you set up in just a few minutes.</p>
                    </div>

                    <div class="space-y-4 mb-8">
                        <div class="flex items-start gap-4 p-4 bg-indigo-50 rounded-xl">
                            <div class="w-10 h-10 bg-indigo-100 rounded-lg flex items-center justify-center shrink-0">
                                <UserIcon class="w-5 h-5 text-indigo-600" />
                            </div>
                            <div>
                                <h3 class="font-semibold text-gray-900">Complete your profile</h3>
                                <p class="text-sm text-gray-600">Add your business details so tenants know who they're renting from.</p>
                            </div>
                        </div>

                        <div class="flex items-start gap-4 p-4 bg-purple-50 rounded-xl">
                            <div class="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center shrink-0">
                                <BuildingOffice2Icon class="w-5 h-5 text-purple-600" />
                            </div>
                            <div>
                                <h3 class="font-semibold text-gray-900">Set up your first property</h3>
                                <p class="text-sm text-gray-600">Configure your buildings, units, and pricing structure.</p>
                            </div>
                        </div>

                        <div class="flex items-start gap-4 p-4 bg-green-50 rounded-xl">
                            <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center shrink-0">
                                <UserPlusIcon class="w-5 h-5 text-green-600" />
                            </div>
                            <div>
                                <h3 class="font-semibold text-gray-900">Invite your first tenant</h3>
                                <p class="text-sm text-gray-600">Optionally send an invitation to start collecting rent right away.</p>
                            </div>
                        </div>
                    </div>

                    <div class="bg-gray-50 rounded-xl p-4 mb-8">
                        <div class="flex items-center gap-3">
                            <ClockIcon class="w-5 h-5 text-gray-400" />
                            <span class="text-sm text-gray-600">This usually takes about <strong>5 minutes</strong></span>
                        </div>
                    </div>

                    <button
                        @click="submitStep"
                        :disabled="form.processing"
                        class="w-full py-4 bg-gradient-to-r from-indigo-600 to-purple-600 text-white rounded-xl font-semibold hover:from-indigo-700 hover:to-purple-700 transition-all flex items-center justify-center gap-2 disabled:opacity-50"
                    >
                        Let's Get Started
                        <ArrowRightIcon class="w-5 h-5" />
                    </button>
                </div>

                <!-- ==================== STEP 2: PROFILE ==================== -->
                <div v-else-if="currentStep === 2" class="p-8">
                    <div class="text-center mb-8">
                        <h1 class="text-2xl font-bold text-gray-900 mb-2">Your Profile</h1>
                        <p class="text-gray-600">Tell us about yourself and your business.</p>
                    </div>

                    <form @submit.prevent="submitStep" class="space-y-6">
                        <!-- Name & Phone -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Your Full Name *</label>
                                <input
                                    v-model="form.name"
                                    type="text"
                                    required
                                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                    placeholder="John Doe"
                                />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Phone Number</label>
                                <input
                                    v-model="form.mobile_number"
                                    type="tel"
                                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                    placeholder="+254 712 345 678"
                                />
                            </div>
                        </div>

                        <!-- Business Info (Optional Section) -->
                        <div class="border-t border-gray-200 pt-6">
                            <h3 class="text-sm font-semibold text-gray-700 mb-4 flex items-center gap-2">
                                <BuildingOfficeIcon class="w-4 h-4" />
                                Business Details (Optional)
                            </h3>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Company Name</label>
                                    <input
                                        v-model="form.company_name"
                                        type="text"
                                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                        placeholder="ABC Properties Ltd"
                                    />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Registration Number</label>
                                    <input
                                        v-model="form.business_registration_number"
                                        type="text"
                                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                        placeholder="BN-123456"
                                    />
                                </div>
                            </div>

                            <div class="mt-4">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Address</label>
                                <textarea
                                    v-model="form.address"
                                    rows="2"
                                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                    placeholder="123 Main Street, Westlands"
                                ></textarea>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">City</label>
                                    <input
                                        v-model="form.city"
                                        type="text"
                                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                        placeholder="Nairobi"
                                    />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Country</label>
                                    <input
                                        v-model="form.country"
                                        type="text"
                                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                        placeholder="Kenya"
                                    />
                                </div>
                            </div>
                        </div>

                        <!-- Navigation -->
                        <div class="flex justify-between pt-6 border-t border-gray-200">
                            <button
                                type="button"
                                @click="goBack"
                                class="px-6 py-3 text-gray-600 hover:text-gray-900 font-medium flex items-center gap-2"
                            >
                                <ArrowLeftIcon class="w-4 h-4" />
                                Back
                            </button>
                            <button
                                type="submit"
                                :disabled="form.processing"
                                class="px-8 py-3 bg-indigo-600 text-white rounded-lg font-semibold hover:bg-indigo-700 transition-all flex items-center gap-2 disabled:opacity-50"
                            >
                                Continue
                                <ArrowRightIcon class="w-4 h-4" />
                            </button>
                        </div>
                    </form>
                </div>

                <!-- ==================== STEP 3: PROPERTY ==================== -->
                <div v-else-if="currentStep === 3" class="p-8">
                    <div class="text-center mb-8">
                        <h1 class="text-2xl font-bold text-gray-900 mb-2">Add Your Property</h1>
                        <p class="text-gray-600">Name your property and select its type.</p>
                    </div>

                    <form @submit.prevent="submitStep" class="space-y-6">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Property Name *</label>
                            <input
                                v-model="form.property_name"
                                type="text"
                                required
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                placeholder="e.g. Sunset Heights Apartments"
                            />
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-3">Property Type *</label>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <!-- Residential -->
                                <div
                                    @click="selectPropertyType('residential')"
                                    :class="form.property_type === 'residential' ? 'ring-2 ring-indigo-600 bg-indigo-50 border-transparent' : 'border-gray-200 hover:border-indigo-300 hover:shadow-md'"
                                    class="relative rounded-xl border p-4 cursor-pointer flex items-start space-x-4 transition-all duration-200"
                                >
                                    <HomeModernIcon class="h-8 w-8 shrink-0" :class="form.property_type === 'residential' ? 'text-indigo-600' : 'text-gray-400'" />
                                    <div>
                                        <span class="block text-sm font-bold text-gray-900">Residential</span>
                                        <span class="block text-xs text-gray-500 mt-1">Multi-story apartments</span>
                                    </div>
                                    <CheckCircleIcon v-if="form.property_type === 'residential'" class="absolute top-4 end-4 h-5 w-5 text-indigo-600" />
                                </div>

                                <!-- Estate -->
                                <div
                                    @click="selectPropertyType('estate')"
                                    :class="form.property_type === 'estate' ? 'ring-2 ring-indigo-600 bg-indigo-50 border-transparent' : 'border-gray-200 hover:border-indigo-300 hover:shadow-md'"
                                    class="relative rounded-xl border p-4 cursor-pointer flex items-start space-x-4 transition-all duration-200"
                                >
                                    <HomeIcon class="h-8 w-8 shrink-0" :class="form.property_type === 'estate' ? 'text-indigo-600' : 'text-gray-400'" />
                                    <div>
                                        <span class="block text-sm font-bold text-gray-900">Gated Estate</span>
                                        <span class="block text-xs text-gray-500 mt-1">Standalone houses/villas</span>
                                    </div>
                                    <CheckCircleIcon v-if="form.property_type === 'estate'" class="absolute top-4 end-4 h-5 w-5 text-indigo-600" />
                                </div>

                                <!-- Commercial -->
                                <div
                                    @click="selectPropertyType('commercial')"
                                    :class="form.property_type === 'commercial' ? 'ring-2 ring-indigo-600 bg-indigo-50 border-transparent' : 'border-gray-200 hover:border-indigo-300 hover:shadow-md'"
                                    class="relative rounded-xl border p-4 cursor-pointer flex items-start space-x-4 transition-all duration-200"
                                >
                                    <BuildingOffice2Icon class="h-8 w-8 shrink-0" :class="form.property_type === 'commercial' ? 'text-indigo-600' : 'text-gray-400'" />
                                    <div>
                                        <span class="block text-sm font-bold text-gray-900">Commercial</span>
                                        <span class="block text-xs text-gray-500 mt-1">Offices, shops, malls</span>
                                    </div>
                                    <CheckCircleIcon v-if="form.property_type === 'commercial'" class="absolute top-4 end-4 h-5 w-5 text-indigo-600" />
                                </div>

                                <!-- Mixed -->
                                <div
                                    @click="selectPropertyType('mixed')"
                                    :class="form.property_type === 'mixed' ? 'ring-2 ring-indigo-600 bg-indigo-50 border-transparent' : 'border-gray-200 hover:border-indigo-300 hover:shadow-md'"
                                    class="relative rounded-xl border p-4 cursor-pointer flex items-start space-x-4 transition-all duration-200"
                                >
                                    <BuildingStorefrontIcon class="h-8 w-8 shrink-0" :class="form.property_type === 'mixed' ? 'text-indigo-600' : 'text-gray-400'" />
                                    <div>
                                        <span class="block text-sm font-bold text-gray-900">Mixed Use</span>
                                        <span class="block text-xs text-gray-500 mt-1">Shops + apartments</span>
                                    </div>
                                    <CheckCircleIcon v-if="form.property_type === 'mixed'" class="absolute top-4 end-4 h-5 w-5 text-indigo-600" />
                                </div>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Property Address</label>
                            <textarea
                                v-model="form.property_address"
                                rows="2"
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                placeholder="123 Main Street, Westlands, Nairobi"
                            ></textarea>
                        </div>

                        <!-- Navigation -->
                        <div class="flex justify-between pt-6 border-t border-gray-200">
                            <button
                                type="button"
                                @click="goBack"
                                class="px-6 py-3 text-gray-600 hover:text-gray-900 font-medium flex items-center gap-2"
                            >
                                <ArrowLeftIcon class="w-4 h-4" />
                                Back
                            </button>
                            <button
                                type="submit"
                                :disabled="form.processing || !form.property_name"
                                class="px-8 py-3 bg-indigo-600 text-white rounded-lg font-semibold hover:bg-indigo-700 transition-all flex items-center gap-2 disabled:opacity-50"
                            >
                                Continue
                                <ArrowRightIcon class="w-4 h-4" />
                            </button>
                        </div>
                    </form>
                </div>

                <!-- ==================== STEP 4: STRUCTURE ==================== -->
                <div v-else-if="currentStep === 4" class="p-8">
                    <div class="text-center mb-8">
                        <h1 class="text-2xl font-bold text-gray-900 mb-2">Property Structure</h1>
                        <p class="text-gray-600">Define your building layout and units.</p>
                    </div>

                    <form @submit.prevent="submitStep" class="space-y-6">
                        <!-- Single vs Multiple Wings Toggle -->
                        <div v-if="form.property_type !== 'estate'" class="flex justify-center gap-4 mb-6">
                            <button
                                type="button"
                                @click="form.has_wings = false"
                                :class="!form.has_wings ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'"
                                class="px-6 py-3 rounded-lg font-medium text-sm transition-colors"
                            >
                                Single Building
                            </button>
                            <button
                                type="button"
                                @click="form.has_wings = true"
                                :class="form.has_wings ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'"
                                class="px-6 py-3 rounded-lg font-medium text-sm transition-colors"
                            >
                                Multiple Wings/Blocks
                            </button>
                        </div>

                        <!-- Single Building Config -->
                        <div v-if="!form.has_wings" class="bg-indigo-50 p-6 rounded-xl border border-indigo-100">
                            <div class="grid grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-1">{{ floorLabel }}</label>
                                    <input
                                        v-model="form.floors"
                                        type="number"
                                        min="1"
                                        max="100"
                                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                    />
                                </div>
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-1">{{ unitLabel }}</label>
                                    <input
                                        v-model="form.units_per_floor"
                                        type="number"
                                        min="1"
                                        max="50"
                                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                    />
                                </div>
                            </div>
                        </div>

                        <!-- Multiple Wings Config -->
                        <div v-else class="space-y-4">
                            <div
                                v-for="(wing, index) in form.wings"
                                :key="index"
                                class="bg-gray-50 p-4 rounded-xl border border-gray-200"
                            >
                                <div class="flex items-center justify-between mb-3">
                                    <span class="font-semibold text-gray-800">Wing {{ index + 1 }}</span>
                                    <button
                                        v-if="form.wings.length > 1"
                                        type="button"
                                        @click="removeWing(index)"
                                        class="text-red-500 hover:text-red-700 p-1"
                                    >
                                        <TrashIcon class="w-5 h-5" />
                                    </button>
                                </div>
                                <div class="grid grid-cols-4 gap-3">
                                    <div class="col-span-2">
                                        <label class="block text-xs font-medium text-gray-600 mb-1">Wing Name</label>
                                        <input
                                            v-model="wing.name"
                                            @blur="updatePrefix(index)"
                                            type="text"
                                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                            placeholder="e.g. Block A"
                                        />
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-600 mb-1">Prefix</label>
                                        <input
                                            v-model="wing.prefix"
                                            type="text"
                                            maxlength="3"
                                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 uppercase"
                                            placeholder="A"
                                        />
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-600 mb-1">Floors</label>
                                        <input
                                            v-model="wing.floors"
                                            type="number"
                                            min="1"
                                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                        />
                                    </div>
                                </div>
                                <div class="mt-3">
                                    <label class="block text-xs font-medium text-gray-600 mb-1">Units per Floor</label>
                                    <input
                                        v-model="wing.units_per_floor"
                                        type="number"
                                        min="1"
                                        class="w-32 px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                    />
                                </div>
                                <p class="text-xs text-gray-500 mt-2">
                                    Units: {{ wing.prefix }}101, {{ wing.prefix }}102... ({{ wing.floors * wing.units_per_floor }} total)
                                </p>
                            </div>

                            <button
                                type="button"
                                @click="addWing"
                                class="w-full py-3 border-2 border-dashed border-gray-300 rounded-xl text-gray-500 hover:border-indigo-400 hover:text-indigo-600 transition-colors flex items-center justify-center gap-2"
                            >
                                <PlusIcon class="w-5 h-5" />
                                Add Another Wing
                            </button>
                        </div>

                        <!-- Summary Box -->
                        <div class="flex items-start p-4 bg-white border border-gray-200 rounded-lg shadow-sm">
                            <div class="shrink-0 text-2xl">🏗️</div>
                            <div class="ms-3 text-sm text-gray-600">
                                <span class="font-semibold text-gray-900">We will generate {{ totalUnits }} units.</span><br />
                                <span class="text-xs">Unit naming: {{ unitNamingPreview }}</span><br />
                                <span class="text-xs text-gray-400">You can edit individual units later using Architect Mode.</span>
                            </div>
                        </div>

                        <!-- Navigation -->
                        <div class="flex justify-between pt-6 border-t border-gray-200">
                            <button
                                type="button"
                                @click="goBack"
                                class="px-6 py-3 text-gray-600 hover:text-gray-900 font-medium flex items-center gap-2"
                            >
                                <ArrowLeftIcon class="w-4 h-4" />
                                Back
                            </button>
                            <button
                                type="submit"
                                :disabled="form.processing"
                                class="px-8 py-3 bg-indigo-600 text-white rounded-lg font-semibold hover:bg-indigo-700 transition-all flex items-center gap-2 disabled:opacity-50"
                            >
                                Continue
                                <ArrowRightIcon class="w-4 h-4" />
                            </button>
                        </div>
                    </form>
                </div>

                <!-- ==================== STEP 5: FINANCIAL ==================== -->
                <div v-else-if="currentStep === 5" class="p-8">
                    <div class="text-center mb-8">
                        <h1 class="text-2xl font-bold text-gray-900 mb-2">Financial Settings</h1>
                        <p class="text-gray-600">Configure your rent and payment options.</p>
                    </div>

                    <form @submit.prevent="submitStep" class="space-y-6">
                        <!-- Default Rent -->
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Default Monthly Rent *</label>
                            <div class="relative">
                                <div class="absolute inset-y-0 start-0 ps-4 flex items-center pointer-events-none">
                                    <span class="text-gray-500 font-bold">{{ currencySymbol }}</span>
                                </div>
                                <input
                                    v-model="form.default_rent"
                                    type="number"
                                    required
                                    min="0"
                                    class="w-full ps-16 pe-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-lg"
                                    placeholder="20000"
                                />
                            </div>
                            <p class="text-xs text-gray-500 mt-1">This will be the default for all units. You can customize individual units later.</p>
                        </div>

                        <!-- Water Billing -->
                        <div class="border-t border-gray-200 pt-6">
                            <h3 class="text-sm font-semibold text-gray-700 mb-3">Water Billing</h3>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                                <button
                                    type="button"
                                    @click="form.water_billing_type = 'consumption'"
                                    :class="form.water_billing_type === 'consumption' ? 'ring-2 ring-indigo-600 bg-indigo-50' : 'bg-gray-50 hover:bg-gray-100'"
                                    class="p-4 rounded-lg border text-start transition-all"
                                >
                                    <div class="font-medium text-gray-900">Per Consumption</div>
                                    <div class="text-xs text-gray-500">Bill based on meter readings</div>
                                </button>
                                <button
                                    type="button"
                                    @click="form.water_billing_type = 'flat_rate'"
                                    :class="form.water_billing_type === 'flat_rate' ? 'ring-2 ring-indigo-600 bg-indigo-50' : 'bg-gray-50 hover:bg-gray-100'"
                                    class="p-4 rounded-lg border text-start transition-all"
                                >
                                    <div class="font-medium text-gray-900">Flat Rate</div>
                                    <div class="text-xs text-gray-500">Fixed monthly charge</div>
                                </button>
                                <button
                                    type="button"
                                    @click="form.water_billing_type = 'none'"
                                    :class="form.water_billing_type === 'none' ? 'ring-2 ring-indigo-600 bg-indigo-50' : 'bg-gray-50 hover:bg-gray-100'"
                                    class="p-4 rounded-lg border text-start transition-all"
                                >
                                    <div class="font-medium text-gray-900">No Water Billing</div>
                                    <div class="text-xs text-gray-500">Water included in rent</div>
                                </button>
                            </div>

                            <!-- Water Rate Input -->
                            <div v-if="form.water_billing_type === 'consumption'" class="mt-4">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Rate per Unit ({{ currencyCode }})</label>
                                <input
                                    v-model="form.water_unit_rate"
                                    type="number"
                                    min="0"
                                    class="w-40 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                />
                            </div>
                            <div v-else-if="form.water_billing_type === 'flat_rate'" class="mt-4">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Monthly Flat Rate ({{ currencyCode }})</label>
                                <input
                                    v-model="form.flat_water_rate"
                                    type="number"
                                    min="0"
                                    class="w-40 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                />
                            </div>
                        </div>

                        <!-- Payment Methods -->
                        <div class="border-t border-gray-200 pt-6">
                            <h3 class="text-sm font-semibold text-gray-700 mb-3">Accepted Payment Methods *</h3>
                            <div class="grid grid-cols-2 gap-3">
                                <button
                                    v-for="method in paymentMethodOptions"
                                    :key="method.id"
                                    type="button"
                                    @click="togglePaymentMethod(method.id)"
                                    :class="form.accepted_payment_methods.includes(method.id) ? 'ring-2 ring-indigo-600 bg-indigo-50 border-indigo-200' : 'bg-white hover:bg-gray-50'"
                                    class="p-4 rounded-lg border flex items-start gap-3 transition-all text-start"
                                >
                                    <component :is="method.icon" class="w-6 h-6 text-gray-400 shrink-0" />
                                    <div>
                                        <div class="font-medium text-gray-900 text-sm">{{ method.label }}</div>
                                        <div class="text-xs text-gray-500">{{ method.description }}</div>
                                    </div>
                                    <CheckCircleSolidIcon
                                        v-if="form.accepted_payment_methods.includes(method.id)"
                                        class="w-5 h-5 text-indigo-600 ms-auto shrink-0"
                                    />
                                </button>
                            </div>
                        </div>

                        <!-- Bank Details (conditionally shown) -->
                        <div v-if="form.accepted_payment_methods.includes('bank_transfer')" class="bg-gray-50 p-4 rounded-lg">
                            <h4 class="text-sm font-medium text-gray-700 mb-3">Bank Account Details</h4>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                                <input
                                    v-model="form.bank_name"
                                    type="text"
                                    placeholder="Bank Name"
                                    class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                />
                                <input
                                    v-model="form.bank_account_name"
                                    type="text"
                                    placeholder="Account Name"
                                    class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                />
                                <input
                                    v-model="form.bank_account_number"
                                    type="text"
                                    placeholder="Account Number"
                                    class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                />
                            </div>
                        </div>

                        <!-- M-Pesa Details -->
                        <div v-if="form.accepted_payment_methods.includes('mobile_money')" class="bg-gray-50 p-4 rounded-lg">
                            <h4 class="text-sm font-medium text-gray-700 mb-3">M-Pesa Details</h4>
                            <input
                                v-model="form.mpesa_paybill"
                                type="text"
                                placeholder="Paybill or Till Number"
                                class="w-full md:w-64 px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                            />
                        </div>

                        <!-- Navigation -->
                        <div class="flex justify-between pt-6 border-t border-gray-200">
                            <button
                                type="button"
                                @click="goBack"
                                class="px-6 py-3 text-gray-600 hover:text-gray-900 font-medium flex items-center gap-2"
                            >
                                <ArrowLeftIcon class="w-4 h-4" />
                                Back
                            </button>
                            <button
                                type="submit"
                                :disabled="form.processing || form.accepted_payment_methods.length === 0"
                                class="px-8 py-3 bg-indigo-600 text-white rounded-lg font-semibold hover:bg-indigo-700 transition-all flex items-center gap-2 disabled:opacity-50"
                            >
                                Continue
                                <ArrowRightIcon class="w-4 h-4" />
                            </button>
                        </div>
                    </form>
                </div>

                <!-- ==================== STEP 6: TEAM (Optional) ==================== -->
                <div v-else-if="currentStep === 6" class="p-8">
                    <div class="text-center mb-8">
                        <div class="inline-flex items-center gap-2 bg-gray-100 text-gray-600 px-3 py-1 rounded-full text-xs font-medium mb-4">
                            Optional Step
                        </div>
                        <h1 class="text-2xl font-bold text-gray-900 mb-2">Invite Your Team</h1>
                        <p class="text-gray-600">Add caretakers to help manage your property.</p>
                    </div>

                    <form @submit.prevent="submitStep" class="space-y-6">
                        <!-- Existing Invitations -->
                        <div v-if="existingInvitations && existingInvitations.length > 0" class="bg-green-50 p-4 rounded-lg mb-6">
                            <h4 class="text-sm font-medium text-green-800 mb-2">Previously Sent Invitations</h4>
                            <ul class="space-y-1">
                                <li v-for="inv in existingInvitations" :key="inv.id" class="text-sm text-green-700 flex items-center gap-2">
                                    <CheckCircleIcon class="w-4 h-4" />
                                    {{ inv.email }}
                                </li>
                            </ul>
                        </div>

                        <!-- Add New Invitations -->
                        <div class="space-y-3">
                            <div
                                v-for="(invitation, index) in form.invitations"
                                :key="index"
                                class="flex items-center gap-3"
                            >
                                <EnvelopeIcon class="w-5 h-5 text-gray-400 shrink-0" />
                                <input
                                    v-model="invitation.email"
                                    type="email"
                                    placeholder="caretaker@example.com"
                                    class="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                />
                                <button
                                    type="button"
                                    @click="removeTeamInvitation(index)"
                                    class="p-2 text-red-500 hover:text-red-700"
                                >
                                    <TrashIcon class="w-5 h-5" />
                                </button>
                            </div>

                            <button
                                type="button"
                                @click="addTeamInvitation"
                                class="w-full py-3 border-2 border-dashed border-gray-300 rounded-lg text-gray-500 hover:border-indigo-400 hover:text-indigo-600 transition-colors flex items-center justify-center gap-2"
                            >
                                <PlusIcon class="w-5 h-5" />
                                Add Caretaker Email
                            </button>
                        </div>

                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                            <p class="text-sm text-blue-800">
                                Caretakers can help you record water readings, manage tickets, and view property information.
                                You can always invite more team members later.
                            </p>
                        </div>

                        <!-- Navigation -->
                        <div class="flex justify-between pt-6 border-t border-gray-200">
                            <button
                                type="button"
                                @click="goBack"
                                class="px-6 py-3 text-gray-600 hover:text-gray-900 font-medium flex items-center gap-2"
                            >
                                <ArrowLeftIcon class="w-4 h-4" />
                                Back
                            </button>
                            <div class="flex gap-3">
                                <button
                                    type="button"
                                    @click="skipStep"
                                    class="px-6 py-3 text-gray-500 hover:text-gray-700 font-medium"
                                >
                                    Skip for now
                                </button>
                                <button
                                    type="submit"
                                    :disabled="form.processing"
                                    class="px-8 py-3 bg-indigo-600 text-white rounded-lg font-semibold hover:bg-indigo-700 transition-all flex items-center gap-2 disabled:opacity-50"
                                >
                                    {{ form.invitations.length > 0 ? 'Send & Continue' : 'Continue' }}
                                    <ArrowRightIcon class="w-4 h-4" />
                                </button>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- ==================== STEP 7: FIRST TENANT (Optional) ==================== -->
                <div v-else-if="currentStep === 7" class="p-8">
                    <div class="text-center mb-8">
                        <div class="inline-flex items-center gap-2 bg-gray-100 text-gray-600 px-3 py-1 rounded-full text-xs font-medium mb-4">
                            Optional Step
                        </div>
                        <h1 class="text-2xl font-bold text-gray-900 mb-2">Invite Your First Tenant</h1>
                        <p class="text-gray-600">Send an invitation to start collecting rent.</p>
                    </div>

                    <form @submit.prevent="submitStep" class="space-y-6">
                        <!-- No Vacant Units Warning -->
                        <div v-if="!vacantUnits || vacantUnits.length === 0" class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 text-center">
                            <p class="text-yellow-800">No vacant units available. Complete the structure step first to create units.</p>
                        </div>

                        <template v-else>
                            <!-- Unit Selection -->
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Select Unit *</label>
                                <select
                                    v-model="form.unit_id"
                                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                >
                                    <option value="">Choose a unit...</option>
                                    <option v-for="unit in vacantUnits" :key="unit.id" :value="unit.id">
                                        {{ unit.unit_number }} - {{ unit.building_name }} ({{ unit.property_name }}) - {{ formatCurrency(unit.target_rent) }}/mo
                                    </option>
                                </select>
                            </div>

                            <!-- Tenant Details -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">Tenant Email *</label>
                                    <input
                                        v-model="form.tenant_email"
                                        type="email"
                                        required
                                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                        placeholder="tenant@example.com"
                                    />
                                </div>
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">Tenant Name</label>
                                    <input
                                        v-model="form.tenant_name"
                                        type="text"
                                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                        placeholder="John Doe"
                                    />
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">Phone Number</label>
                                    <input
                                        v-model="form.tenant_phone"
                                        type="tel"
                                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                        placeholder="+254..."
                                    />
                                </div>
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">Monthly Rent *</label>
                                    <input
                                        v-model="form.rent_amount"
                                        type="number"
                                        required
                                        min="0"
                                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                    />
                                </div>
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">Deposit *</label>
                                    <input
                                        v-model="form.deposit_amount"
                                        type="number"
                                        required
                                        min="0"
                                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                    />
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Lease Start Date *</label>
                                <input
                                    v-model="form.start_date"
                                    type="date"
                                    required
                                    class="w-full md:w-64 px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                />
                            </div>
                        </template>

                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                            <p class="text-sm text-blue-800">
                                The tenant will receive an email invitation to accept the lease and create their account.
                                You can always invite more tenants from the Tenants page.
                            </p>
                        </div>

                        <!-- Navigation -->
                        <div class="flex justify-between pt-6 border-t border-gray-200">
                            <button
                                type="button"
                                @click="goBack"
                                class="px-6 py-3 text-gray-600 hover:text-gray-900 font-medium flex items-center gap-2"
                            >
                                <ArrowLeftIcon class="w-4 h-4" />
                                Back
                            </button>
                            <div class="flex gap-3">
                                <button
                                    type="button"
                                    @click="skipStep"
                                    class="px-6 py-3 text-gray-500 hover:text-gray-700 font-medium"
                                >
                                    Skip for now
                                </button>
                                <button
                                    type="submit"
                                    :disabled="form.processing || !form.unit_id"
                                    class="px-8 py-3 bg-indigo-600 text-white rounded-lg font-semibold hover:bg-indigo-700 transition-all flex items-center gap-2 disabled:opacity-50"
                                >
                                    Send Invitation
                                    <ArrowRightIcon class="w-4 h-4" />
                                </button>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- ==================== STEP 8: COMPLETE ==================== -->
                <div v-else-if="currentStep === 8" class="p-8">
                    <div class="text-center mb-8">
                        <div class="w-24 h-24 bg-gradient-to-br from-green-400 to-emerald-600 rounded-full flex items-center justify-center mx-auto mb-6 animate-bounce-slow">
                            <CheckCircleSolidIcon class="w-14 h-14 text-white" />
                        </div>
                        <h1 class="text-3xl font-bold text-gray-900 mb-2">You're All Set!</h1>
                        <p class="text-lg text-gray-600">Your property is ready to go.</p>
                    </div>

                    <!-- Summary Stats -->
                    <div v-if="summary" class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
                        <div class="bg-indigo-50 rounded-xl p-4 text-center">
                            <div class="text-3xl font-bold text-indigo-600">{{ summary.properties }}</div>
                            <div class="text-sm text-gray-600">Properties</div>
                        </div>
                        <div class="bg-purple-50 rounded-xl p-4 text-center">
                            <div class="text-3xl font-bold text-purple-600">{{ summary.buildings }}</div>
                            <div class="text-sm text-gray-600">Buildings</div>
                        </div>
                        <div class="bg-green-50 rounded-xl p-4 text-center">
                            <div class="text-3xl font-bold text-green-600">{{ summary.units }}</div>
                            <div class="text-sm text-gray-600">Units</div>
                        </div>
                        <div class="bg-amber-50 rounded-xl p-4 text-center">
                            <div class="text-3xl font-bold text-amber-600">
                                <CheckIcon v-if="summary.hasPaymentConfig" class="w-8 h-8 mx-auto text-amber-600" />
                                <XMarkIcon v-else class="w-8 h-8 mx-auto text-gray-400" />
                            </div>
                            <div class="text-sm text-gray-600">Payment Setup</div>
                        </div>
                    </div>

                    <!-- What's Next -->
                    <div class="bg-gray-50 rounded-xl p-6 mb-8">
                        <h3 class="font-semibold text-gray-900 mb-4">What's Next?</h3>
                        <ul class="space-y-3">
                            <li class="flex items-start gap-3">
                                <div class="w-6 h-6 bg-indigo-100 rounded-full flex items-center justify-center shrink-0 mt-0.5">
                                    <span class="text-xs font-bold text-indigo-600">1</span>
                                </div>
                                <div>
                                    <div class="font-medium text-gray-900">Explore your dashboard</div>
                                    <div class="text-sm text-gray-500">See your units and their status at a glance</div>
                                </div>
                            </li>
                            <li class="flex items-start gap-3">
                                <div class="w-6 h-6 bg-indigo-100 rounded-full flex items-center justify-center shrink-0 mt-0.5">
                                    <span class="text-xs font-bold text-indigo-600">2</span>
                                </div>
                                <div>
                                    <div class="font-medium text-gray-900">Customize individual units</div>
                                    <div class="text-sm text-gray-500">Adjust rent, status, and details per unit</div>
                                </div>
                            </li>
                            <li class="flex items-start gap-3">
                                <div class="w-6 h-6 bg-indigo-100 rounded-full flex items-center justify-center shrink-0 mt-0.5">
                                    <span class="text-xs font-bold text-indigo-600">3</span>
                                </div>
                                <div>
                                    <div class="font-medium text-gray-900">Invite more tenants</div>
                                    <div class="text-sm text-gray-500">Fill your vacant units and start collecting rent</div>
                                </div>
                            </li>
                        </ul>
                    </div>

                    <!-- CTA Button -->
                    <button
                        @click="completeOnboarding"
                        class="w-full py-4 bg-gradient-to-r from-green-500 to-emerald-600 text-white rounded-xl font-bold text-lg hover:from-green-600 hover:to-emerald-700 transition-all flex items-center justify-center gap-2 shadow-lg"
                    >
                        <RocketLaunchIcon class="w-6 h-6" />
                        Go to Dashboard
                    </button>
                </div>

            </div>
        </div>
    </div>
</template>

<style scoped>
.animate-bounce-slow {
    animation: bounce 2s infinite;
}

@keyframes bounce {
    0%, 100% {
        transform: translateY(0);
    }
    50% {
        transform: translateY(-10px);
    }
}
</style>
