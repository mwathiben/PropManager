<script setup lang="ts">
import { computed } from 'vue';

interface SankeyNode {
    id: string;
    label: string;
    count: number;
}

interface SankeyLink {
    source: string;
    target: string;
    value: number;
}

const props = defineProps<{
    payload: {
        nodes: SankeyNode[];
        links: SankeyLink[];
        window_days: number;
    };
    width?: number;
    height?: number;
}>();

const width = computed(() => props.width ?? 720);
const height = computed(() => props.height ?? 360);
const bandWidth = 16;
const padding = { top: 20, bottom: 20, left: 80, right: 120 };

const orderedStages = ['signup', 'onboarding_complete', 'first_payment', 'retained_60d'] as const;

const layout = computed(() => {
    const nodesById: Record<string, SankeyNode & { x: number; y: number; h: number }> = {};
    const innerW = width.value - padding.left - padding.right;
    const innerH = height.value - padding.top - padding.bottom;

    const columns: Array<{ stageId: string; nodes: SankeyNode[] }> = orderedStages.map((stageId) => ({
        stageId,
        nodes: props.payload.nodes.filter((n) => n.id === stageId || n.id === `dropped_at_${stageId}`),
    }));

    const maxColCount = Math.max(...columns.map((c) => c.nodes.reduce((sum, n) => sum + n.count, 0)), 1);
    const xStep = innerW / (orderedStages.length - 1);

    columns.forEach((column, colIdx) => {
        const x = padding.left + colIdx * xStep;
        const totalCount = column.nodes.reduce((s, n) => s + n.count, 0);
        const gap = totalCount > 0 ? Math.min(6, innerH * 0.02) : 0;
        let cursorY = padding.top;
        column.nodes.forEach((node) => {
            const h = totalCount > 0 ? (node.count / maxColCount) * innerH : 0;
            nodesById[node.id] = { ...node, x, y: cursorY, h };
            cursorY += h + gap;
        });
    });

    const linksWithGeometry = props.payload.links
        .map((link) => {
            const src = nodesById[link.source];
            const tgt = nodesById[link.target];
            if (!src || !tgt || link.value <= 0) return null;

            const srcCount = src.count || 1;
            const tgtCount = tgt.count || 1;
            const srcSliceH = (link.value / srcCount) * src.h;
            const tgtSliceH = (link.value / tgtCount) * tgt.h;
            const x1 = src.x + bandWidth;
            const y1 = src.y + srcSliceH / 2;
            const x2 = tgt.x;
            const y2 = tgt.y + tgtSliceH / 2;
            const cx = (x1 + x2) / 2;
            const isDropOff = link.target.startsWith('dropped_at_');

            return {
                ...link,
                d: `M ${x1} ${y1} C ${cx} ${y1} ${cx} ${y2} ${x2} ${y2}`,
                thickness: Math.max(srcSliceH, tgtSliceH, 2),
                isDropOff,
            };
        })
        .filter((l): l is NonNullable<typeof l> => l !== null);

    return { nodesById, links: linksWithGeometry };
});

const nodeFill = (id: string): string =>
    id.startsWith('dropped_at_') ? '#9CA3AF' : '#10B981';

const linkStroke = (isDropOff: boolean): string =>
    isDropOff ? '#D1D5DB' : '#A7F3D0';
</script>

<template>
    <svg
        :width="width"
        :height="height"
        role="img"
        :aria-label="`Funnel sankey over the last ${payload.window_days} days`"
        data-testid="funnel-sankey-svg"
    >
        <path
            v-for="link in layout.links"
            :key="`${link.source}-${link.target}`"
            :d="link.d"
            fill="none"
            :stroke="linkStroke(link.isDropOff)"
            :stroke-width="link.thickness"
            stroke-opacity="0.6"
        >
            <title>{{ link.source }} → {{ link.target }}: {{ link.value }}</title>
        </path>
        <g v-for="node in Object.values(layout.nodesById)" :key="node.id">
            <rect
                :x="node.x"
                :y="node.y"
                :width="bandWidth"
                :height="node.h"
                :fill="nodeFill(node.id)"
                :aria-label="`${node.label}: ${node.count} users`"
            >
                <title>{{ node.label }}: {{ node.count }}</title>
            </rect>
            <text
                :x="node.x + bandWidth + 4"
                :y="node.y + Math.max(node.h / 2, 8)"
                font-size="11"
                dominant-baseline="middle"
                fill="#374151"
            >
                {{ node.label }} ({{ node.count }})
            </text>
        </g>
    </svg>
</template>
