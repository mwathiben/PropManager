<script setup>
import { ref, onMounted, onUnmounted, watch } from 'vue';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import { MapPinIcon, ArrowTopRightOnSquareIcon } from '@heroicons/vue/24/outline';

// Fix Leaflet default marker icon issue
delete L.Icon.Default.prototype._getIconUrl;
L.Icon.Default.mergeOptions({
    iconRetinaUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-icon-2x.png',
    iconUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-icon.png',
    shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-shadow.png',
});

const props = defineProps({
    coordinates: {
        type: Object,
        default: null, // { lat: number, lng: number }
    },
    address: {
        type: String,
        default: '',
    },
    editable: {
        type: Boolean,
        default: false,
    },
    height: {
        type: String,
        default: '300px',
    },
});

const emit = defineEmits(['update:coordinates']);

const mapContainer = ref(null);
let map = null;
let marker = null;

// Default center (Nairobi, Kenya)
const defaultCenter = { lat: -1.2921, lng: 36.8219 };
const defaultZoom = 13;

const getCenter = () => {
    if (props.coordinates?.lat && props.coordinates?.lng) {
        return [props.coordinates.lat, props.coordinates.lng];
    }
    return [defaultCenter.lat, defaultCenter.lng];
};

const initMap = () => {
    if (!mapContainer.value || map) return;

    const center = getCenter();

    map = L.map(mapContainer.value, {
        center: center,
        zoom: props.coordinates?.lat ? 16 : defaultZoom,
        scrollWheelZoom: props.editable,
    });

    // Add OpenStreetMap tiles
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
        maxZoom: 19,
    }).addTo(map);

    // Add marker if coordinates exist
    if (props.coordinates?.lat && props.coordinates?.lng) {
        addMarker(props.coordinates.lat, props.coordinates.lng);
    }

    // Handle click for editable mode
    if (props.editable) {
        map.on('click', (e) => {
            const { lat, lng } = e.latlng;
            addMarker(lat, lng);
            emit('update:coordinates', { lat, lng });
        });
    }
};

const addMarker = (lat, lng) => {
    // Remove existing marker
    if (marker) {
        map.removeLayer(marker);
    }

    marker = L.marker([lat, lng], {
        draggable: props.editable,
    }).addTo(map);

    if (props.editable) {
        marker.on('dragend', (e) => {
            const pos = e.target.getLatLng();
            emit('update:coordinates', { lat: pos.lat, lng: pos.lng });
        });
    }

    // Center map on marker
    map.setView([lat, lng], map.getZoom() < 14 ? 16 : map.getZoom());
};

const openInGoogleMaps = () => {
    if (props.coordinates?.lat && props.coordinates?.lng) {
        const url = `https://www.google.com/maps/search/?api=1&query=${props.coordinates.lat},${props.coordinates.lng}`;
        window.open(url, '_blank');
    }
};

const searchAddress = async () => {
    if (!props.address) return;

    try {
        const response = await fetch(
            `https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(props.address)}&limit=1`
        );
        const data = await response.json();

        if (data.length > 0) {
            const { lat, lon } = data[0];
            addMarker(parseFloat(lat), parseFloat(lon));
            emit('update:coordinates', { lat: parseFloat(lat), lng: parseFloat(lon) });
        }
    } catch (error) {
        console.error('Address search failed:', error);
    }
};

// Watch for coordinate changes
watch(() => props.coordinates, (newCoords) => {
    if (map && newCoords?.lat && newCoords?.lng) {
        addMarker(newCoords.lat, newCoords.lng);
    }
}, { deep: true });

onMounted(() => {
    // Delay initialization to ensure DOM is ready
    setTimeout(initMap, 100);
});

onUnmounted(() => {
    if (map) {
        map.remove();
        map = null;
    }
});
</script>

<template>
    <div class="relative">
        <!-- Map Container -->
        <div
            ref="mapContainer"
            :style="{ height: height }"
            class="w-full rounded-lg overflow-hidden border border-gray-200"
        ></div>

        <!-- No coordinates placeholder -->
        <div
            v-if="!coordinates?.lat"
            class="absolute inset-0 flex items-center justify-center bg-gray-100 rounded-lg"
        >
            <div class="text-center">
                <MapPinIcon class="w-12 h-12 mx-auto text-gray-300" />
                <p class="mt-2 text-sm text-gray-500">
                    {{ editable ? 'Click on the map to set location' : 'No location set' }}
                </p>
                <button
                    v-if="editable && address"
                    @click="searchAddress"
                    class="mt-3 px-4 py-2 text-sm text-indigo-600 hover:text-indigo-800 font-medium"
                >
                    Search by address
                </button>
            </div>
        </div>

        <!-- Action Buttons (when coordinates exist) -->
        <div v-if="coordinates?.lat && coordinates?.lng" class="absolute top-3 right-3 flex gap-2">
            <button
                @click="openInGoogleMaps"
                class="p-2 bg-white rounded-lg shadow-md hover:bg-gray-50 transition"
                title="Open in Google Maps"
            >
                <ArrowTopRightOnSquareIcon class="w-5 h-5 text-gray-600" />
            </button>
        </div>

        <!-- Coordinates Display -->
        <div v-if="coordinates?.lat && coordinates?.lng" class="absolute bottom-3 left-3 bg-white/90 backdrop-blur-sm rounded-lg px-3 py-2 shadow-sm">
            <div class="text-xs text-gray-600">
                {{ coordinates.lat.toFixed(6) }}, {{ coordinates.lng.toFixed(6) }}
            </div>
        </div>

        <!-- Editable Instructions -->
        <div v-if="editable && coordinates?.lat" class="absolute bottom-3 right-3 bg-indigo-600/90 backdrop-blur-sm text-white text-xs px-3 py-2 rounded-lg">
            Drag marker to adjust
        </div>
    </div>
</template>

<style>
/* Fix z-index issues with Leaflet controls */
.leaflet-control-container .leaflet-control {
    z-index: 10;
}
</style>
