<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { StarIcon } from '@heroicons/vue/24/solid';
import { StarIcon as StarOutlineIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    ticketId: {
        type: Number,
        required: true
    }
});

const emit = defineEmits(['submitted']);

const form = useForm({
    rating: 0,
    comments: ''
});

const hoveredRating = ref(0);

const setRating = (rating) => {
    form.rating = rating;
};

const submit = () => {
    form.post(route('tickets.feedback', props.ticketId), {
        preserveScroll: true,
        onSuccess: () => {
            emit('submitted');
        }
    });
};
</script>

<template>
    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
        <h4 class="text-sm font-medium text-yellow-800 mb-3">How satisfied are you with the resolution?</h4>

        <form @submit.prevent="submit">
            <!-- Star Rating -->
            <div class="flex items-center space-x-1 mb-4">
                <button
                    v-for="star in 5"
                    :key="star"
                    type="button"
                    @click="setRating(star)"
                    @mouseenter="hoveredRating = star"
                    @mouseleave="hoveredRating = 0"
                    class="focus:outline-none"
                >
                    <StarIcon
                        v-if="star <= (hoveredRating || form.rating)"
                        class="h-8 w-8 text-yellow-400"
                    />
                    <StarOutlineIcon
                        v-else
                        class="h-8 w-8 text-gray-300 hover:text-yellow-400"
                    />
                </button>
                <span v-if="form.rating" class="ml-2 text-sm text-gray-600">
                    {{ ['', 'Very Poor', 'Poor', 'Average', 'Good', 'Excellent'][form.rating] }}
                </span>
            </div>

            <div v-if="form.errors.rating" class="text-red-600 text-sm mb-2">
                {{ form.errors.rating }}
            </div>

            <!-- Comments -->
            <div class="mb-4">
                <label for="feedback-comments" class="block text-sm font-medium text-gray-700 mb-1">
                    Additional Comments (optional)
                </label>
                <textarea
                    id="feedback-comments"
                    v-model="form.comments"
                    rows="3"
                    class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                    placeholder="Tell us about your experience..."
                />
            </div>

            <button
                type="submit"
                :disabled="!form.rating || form.processing"
                class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-yellow-600 hover:bg-yellow-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-yellow-500 disabled:opacity-50 disabled:cursor-not-allowed"
            >
                <span v-if="form.processing">Submitting...</span>
                <span v-else>Submit Feedback</span>
            </button>
        </form>
    </div>
</template>
