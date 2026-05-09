<script setup lang="ts">
import { useFormatters } from '@/composables';
import type { TenantNotesContactsTabProps } from '@/types';

const props = defineProps<TenantNotesContactsTabProps>();
const { formatDateTime: formatDate } = useFormatters();

const pinnedNotes = () => (props.tenantNotes || []).filter(n => n.is_pinned);
const regularNotes = () => (props.tenantNotes || []).filter(n => !n.is_pinned);
</script>

<template>
    <div class="space-y-6">
        <div class="bg-white border rounded-lg overflow-hidden">
            <div class="border-b bg-gray-50 px-4 py-3 flex items-center justify-between">
                <h3 class="text-sm font-medium text-gray-900">Emergency Contacts</h3>
                <span class="text-xs text-gray-500">{{ emergencyContacts?.length || 0 }} contacts</span>
            </div>
            <div v-if="!emergencyContacts?.length" class="p-8 text-center text-gray-500">
                <p class="text-sm">No emergency contacts added.</p>
            </div>
            <ul v-else class="divide-y">
                <li v-for="contact in emergencyContacts" :key="contact.id" class="p-4">
                    <div class="flex items-start gap-3">
                        <div class="h-10 w-10 rounded-full bg-gray-100 flex items-center justify-center shrink-0">
                            <svg class="h-5 w-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                            </svg>
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2">
                                <p class="text-sm font-medium text-gray-900">{{ contact.name }}</p>
                                <span v-if="contact.is_primary" class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800">
                                    Primary
                                </span>
                            </div>
                            <p class="text-xs text-gray-500">{{ contact.relationship }}</p>
                            <div class="mt-2 space-y-1">
                                <p class="text-sm text-gray-700">
                                    <span class="text-gray-500">Phone:</span> {{ contact.phone }}
                                </p>
                                <p v-if="contact.email" class="text-sm text-gray-700">
                                    <span class="text-gray-500">Email:</span> {{ contact.email }}
                                </p>
                            </div>
                        </div>
                    </div>
                </li>
            </ul>
        </div>

        <div class="bg-white border rounded-lg overflow-hidden">
            <div class="border-b bg-gray-50 px-4 py-3 flex items-center justify-between">
                <h3 class="text-sm font-medium text-gray-900">Private Notes</h3>
                <span class="text-xs text-gray-500">{{ tenantNotes?.length || 0 }} notes</span>
            </div>
            <div v-if="!tenantNotes?.length" class="p-8 text-center text-gray-500">
                <p class="text-sm">No notes added.</p>
            </div>
            <div v-else class="divide-y max-h-96 overflow-y-auto">
                <div v-if="pinnedNotes().length" class="bg-yellow-50">
                    <div v-for="note in pinnedNotes()" :key="note.id" class="p-4 border-b border-yellow-100 last:border-b-0">
                        <div class="flex items-start gap-2">
                            <svg class="h-4 w-4 text-yellow-500 shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M5 5a2 2 0 012-2h6a2 2 0 012 2v3a2 2 0 01-2 2h-1v1a3 3 0 11-6 0v-1H5a2 2 0 01-2-2V5z" />
                            </svg>
                            <div class="min-w-0 flex-1">
                                <p class="text-sm text-gray-900 whitespace-pre-wrap">{{ note.content }}</p>
                                <p class="text-xs text-gray-500 mt-2">
                                    {{ note.author?.name || 'Unknown' }} &middot; {{ formatDate(note.created_at) }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
                <div v-for="note in regularNotes()" :key="note.id" class="p-4">
                    <p class="text-sm text-gray-900 whitespace-pre-wrap">{{ note.content }}</p>
                    <p class="text-xs text-gray-500 mt-2">
                        {{ note.author?.name || 'Unknown' }} &middot; {{ formatDate(note.created_at) }}
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>
