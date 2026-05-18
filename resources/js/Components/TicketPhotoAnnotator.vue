<script setup lang="ts">
/**
 * Phase-45 TICKET-PHOTOS-1: maintenance-ticket photo annotation editor.
 *
 * Native HTML5 canvas, no Fabric.js / Konva dependency — the tool set
 * (pen, rect, arrow, text) is small enough that hand-rolled drawing
 * keeps the bundle lean. Saves a PNG snapshot + the scene JSON
 * (annotation_data) so the editor can hydrate for re-edits later.
 *
 * Mount strategy: this component is dynamic-imported from
 * Pages/Tickets/Show.vue 'Annotate' button so the canvas code only
 * lands on the wire for users who actually use it.
 */
import { ref, computed, onMounted, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { enqueuePhoto, discardPhoto, markUploading, markFailed } from '@/lib/offlinePhotoStore';

interface Props {
    ticketId: number;
    document: {
        id: number;
        file_path: string;
        file_url: string;
        annotation_data?: Annotation[] | null;
    };
}

type Tool = 'pen' | 'rect' | 'arrow' | 'text';
type Annotation =
    | { kind: 'pen'; color: string; width: number; points: { x: number; y: number }[] }
    | { kind: 'rect'; color: string; width: number; x: number; y: number; w: number; h: number }
    | { kind: 'arrow'; color: string; width: number; x1: number; y1: number; x2: number; y2: number }
    | { kind: 'text'; color: string; size: number; x: number; y: number; text: string };

const props = defineProps<Props>();
const emit = defineEmits<{ (e: 'saved'): void; (e: 'cancel'): void }>();

const canvasRef = ref<HTMLCanvasElement | null>(null);
const tool = ref<Tool>('pen');
const color = ref('#ef4444');
const width = ref(4);
const isDrawing = ref(false);
const isSaving = ref(false);
const annotations = ref<Annotation[]>([]);
const currentStroke = ref<{ x: number; y: number }[]>([]);
const dragStart = ref<{ x: number; y: number } | null>(null);
const dragEnd = ref<{ x: number; y: number } | null>(null);

const baseImage = new Image();
baseImage.crossOrigin = 'anonymous';

onMounted(() => {
    if (props.document.annotation_data) {
        annotations.value = [...props.document.annotation_data];
    }
    baseImage.onload = () => {
        const canvas = canvasRef.value;
        if (!canvas) return;
        canvas.width = baseImage.width;
        canvas.height = baseImage.height;
        redraw();
    };
    baseImage.src = props.document.file_url;
});

watch(annotations, redraw, { deep: true });

function redraw(): void {
    const canvas = canvasRef.value;
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    if (!ctx) return;
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    ctx.drawImage(baseImage, 0, 0, canvas.width, canvas.height);
    for (const ann of annotations.value) {
        drawAnnotation(ctx, ann);
    }
    // Preview the in-progress drag
    if (dragStart.value && dragEnd.value) {
        if (tool.value === 'rect') {
            drawAnnotation(ctx, {
                kind: 'rect',
                color: color.value,
                width: width.value,
                x: dragStart.value.x,
                y: dragStart.value.y,
                w: dragEnd.value.x - dragStart.value.x,
                h: dragEnd.value.y - dragStart.value.y,
            });
        } else if (tool.value === 'arrow') {
            drawAnnotation(ctx, {
                kind: 'arrow',
                color: color.value,
                width: width.value,
                x1: dragStart.value.x,
                y1: dragStart.value.y,
                x2: dragEnd.value.x,
                y2: dragEnd.value.y,
            });
        }
    }
    // Preview the in-progress pen stroke
    if (tool.value === 'pen' && currentStroke.value.length > 1) {
        drawAnnotation(ctx, {
            kind: 'pen',
            color: color.value,
            width: width.value,
            points: currentStroke.value,
        });
    }
}

function drawAnnotation(ctx: CanvasRenderingContext2D, ann: Annotation): void {
    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';
    if (ann.kind === 'pen') {
        ctx.strokeStyle = ann.color;
        ctx.lineWidth = ann.width;
        ctx.beginPath();
        for (let i = 0; i < ann.points.length; i++) {
            const p = ann.points[i];
            if (i === 0) ctx.moveTo(p.x, p.y);
            else ctx.lineTo(p.x, p.y);
        }
        ctx.stroke();
    } else if (ann.kind === 'rect') {
        ctx.strokeStyle = ann.color;
        ctx.lineWidth = ann.width;
        ctx.strokeRect(ann.x, ann.y, ann.w, ann.h);
    } else if (ann.kind === 'arrow') {
        ctx.strokeStyle = ann.color;
        ctx.fillStyle = ann.color;
        ctx.lineWidth = ann.width;
        ctx.beginPath();
        ctx.moveTo(ann.x1, ann.y1);
        ctx.lineTo(ann.x2, ann.y2);
        ctx.stroke();
        const angle = Math.atan2(ann.y2 - ann.y1, ann.x2 - ann.x1);
        const head = Math.max(12, ann.width * 3);
        ctx.beginPath();
        ctx.moveTo(ann.x2, ann.y2);
        ctx.lineTo(ann.x2 - head * Math.cos(angle - Math.PI / 6), ann.y2 - head * Math.sin(angle - Math.PI / 6));
        ctx.lineTo(ann.x2 - head * Math.cos(angle + Math.PI / 6), ann.y2 - head * Math.sin(angle + Math.PI / 6));
        ctx.closePath();
        ctx.fill();
    } else if (ann.kind === 'text') {
        ctx.fillStyle = ann.color;
        ctx.font = `${ann.size}px sans-serif`;
        ctx.fillText(ann.text, ann.x, ann.y);
    }
}

function pointFromEvent(e: PointerEvent): { x: number; y: number } {
    const canvas = canvasRef.value!;
    const rect = canvas.getBoundingClientRect();
    const scaleX = canvas.width / rect.width;
    const scaleY = canvas.height / rect.height;
    return {
        x: (e.clientX - rect.left) * scaleX,
        y: (e.clientY - rect.top) * scaleY,
    };
}

function onPointerDown(e: PointerEvent): void {
    e.preventDefault();
    isDrawing.value = true;
    const p = pointFromEvent(e);
    if (tool.value === 'pen') {
        currentStroke.value = [p];
    } else if (tool.value === 'rect' || tool.value === 'arrow') {
        dragStart.value = p;
        dragEnd.value = p;
    } else if (tool.value === 'text') {
        const text = window.prompt('Enter annotation text:');
        if (text && text.trim() !== '') {
            annotations.value.push({
                kind: 'text',
                color: color.value,
                size: Math.max(16, width.value * 4),
                x: p.x,
                y: p.y,
                text: text.trim(),
            });
        }
        isDrawing.value = false;
    }
}

function onPointerMove(e: PointerEvent): void {
    if (!isDrawing.value) return;
    const p = pointFromEvent(e);
    if (tool.value === 'pen') {
        currentStroke.value.push(p);
        redraw();
    } else if (tool.value === 'rect' || tool.value === 'arrow') {
        dragEnd.value = p;
        redraw();
    }
}

function onPointerUp(): void {
    if (!isDrawing.value) return;
    isDrawing.value = false;
    if (tool.value === 'pen' && currentStroke.value.length > 1) {
        annotations.value.push({
            kind: 'pen',
            color: color.value,
            width: width.value,
            points: [...currentStroke.value],
        });
        currentStroke.value = [];
    } else if (tool.value === 'rect' && dragStart.value && dragEnd.value) {
        annotations.value.push({
            kind: 'rect',
            color: color.value,
            width: width.value,
            x: dragStart.value.x,
            y: dragStart.value.y,
            w: dragEnd.value.x - dragStart.value.x,
            h: dragEnd.value.y - dragStart.value.y,
        });
    } else if (tool.value === 'arrow' && dragStart.value && dragEnd.value) {
        annotations.value.push({
            kind: 'arrow',
            color: color.value,
            width: width.value,
            x1: dragStart.value.x,
            y1: dragStart.value.y,
            x2: dragEnd.value.x,
            y2: dragEnd.value.y,
        });
    }
    dragStart.value = null;
    dragEnd.value = null;
}

function undo(): void {
    annotations.value.pop();
}

function clearAll(): void {
    annotations.value = [];
}

async function save(): Promise<void> {
    const canvas = canvasRef.value;
    if (!canvas) return;
    isSaving.value = true;

    // Phase-62 OFFLINE-PHOTOS-1: persist the blob to IDB FIRST so a
    // network blip doesn't throw away the annotation. The IDB entry
    // is the retry handle; on successful upload we delete it.
    const blob = await new Promise<Blob | null>((resolve) => canvas.toBlob(resolve, 'image/png'));
    let offlineKey: string | null = null;
    if (blob) {
        try {
            offlineKey = await enqueuePhoto({
                ticketId: props.ticketId,
                documentId: props.document.id,
                blob,
                annotationData: annotations.value,
            });
            await markUploading(offlineKey);
        } catch (e) {
            // Quota exceeded — fall through to upload-only path. The
            // user still sees their save attempt; we just can't queue
            // it for retry if the network fails.
            offlineKey = null;
        }
    }

    const dataUrl = canvas.toDataURL('image/png');
    router.post(
        route('tickets.attachments.annotation', { ticket: props.ticketId, document: props.document.id }),
        {
            image: dataUrl,
            annotation_data: annotations.value,
        },
        {
            preserveScroll: true,
            onFinish: () => {
                isSaving.value = false;
            },
            onSuccess: () => {
                if (offlineKey) {
                    void discardPhoto(offlineKey);
                }
                emit('saved');
            },
            onError: (errors) => {
                if (offlineKey) {
                    void markFailed(offlineKey, JSON.stringify(errors));
                }
            },
        },
    );
}

const hasAnnotations = computed(() => annotations.value.length > 0);
</script>

<template>
    <div class="bg-white rounded-lg shadow p-4 space-y-4">
        <div class="flex flex-wrap items-center gap-3 border-b pb-3">
            <div class="flex gap-1" role="toolbar" aria-label="Annotation tools">
                <button type="button" @click="tool = 'pen'" :class="['px-3 py-1.5 rounded text-sm', tool === 'pen' ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-700']" :aria-pressed="tool === 'pen'">Pen</button>
                <button type="button" @click="tool = 'rect'" :class="['px-3 py-1.5 rounded text-sm', tool === 'rect' ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-700']" :aria-pressed="tool === 'rect'">Rect</button>
                <button type="button" @click="tool = 'arrow'" :class="['px-3 py-1.5 rounded text-sm', tool === 'arrow' ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-700']" :aria-pressed="tool === 'arrow'">Arrow</button>
                <button type="button" @click="tool = 'text'" :class="['px-3 py-1.5 rounded text-sm', tool === 'text' ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-700']" :aria-pressed="tool === 'text'">Text</button>
            </div>
            <label class="flex items-center gap-2 text-sm text-gray-700">
                <span>Color</span>
                <input v-model="color" type="color" class="h-8 w-10 cursor-pointer border border-gray-200 rounded">
            </label>
            <label class="flex items-center gap-2 text-sm text-gray-700">
                <span>Width</span>
                <input v-model.number="width" type="range" min="1" max="20" class="w-24">
                <span class="tabular-nums w-6 text-end">{{ width }}</span>
            </label>
            <button type="button" @click="undo" :disabled="!hasAnnotations" class="ms-auto px-3 py-1.5 rounded text-sm bg-gray-100 text-gray-700 disabled:opacity-50">Undo</button>
            <button type="button" @click="clearAll" :disabled="!hasAnnotations" class="px-3 py-1.5 rounded text-sm bg-gray-100 text-gray-700 disabled:opacity-50">Clear</button>
        </div>

        <div class="overflow-auto bg-gray-50 rounded">
            <canvas
                ref="canvasRef"
                @pointerdown="onPointerDown"
                @pointermove="onPointerMove"
                @pointerup="onPointerUp"
                @pointerleave="onPointerUp"
                class="block touch-none"
                style="max-width: 100%; height: auto;"
                aria-label="Photo annotation canvas"
            />
        </div>

        <div class="flex justify-end gap-2 border-t pt-3">
            <button type="button" @click="emit('cancel')" class="px-4 py-2 rounded text-sm bg-gray-100 text-gray-700">Cancel</button>
            <button type="button" @click="save" :disabled="isSaving || !hasAnnotations" class="px-4 py-2 rounded text-sm bg-indigo-600 text-white disabled:opacity-50">
                {{ isSaving ? 'Saving...' : 'Save annotation' }}
            </button>
        </div>
    </div>
</template>
