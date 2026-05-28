<template>
    <div class="location-picker-container">
        <label class="form-label d-flex justify-content-between align-items-center">
            <span>{{ label }}</span>
            <small class="text-muted" v-if="hint">{{ hint }}</small>
        </label>
        
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body p-0">
                <div ref="mapContainer" class="map-container"></div>
                
                <div class="map-controls p-2 bg-light border-top d-flex align-items-center justify-content-between">
                    <small class="text-muted" v-if="internalLat && internalLng">
                        Selected: {{ internalLat.toFixed(6) }}, {{ internalLng.toFixed(6) }}
                    </small>
                    <small class="text-muted" v-else>
                        Click on map to select location
                    </small>
                    
                    <div class="d-flex gap-2 align-items-center">
                        <div class="btn-group btn-group-sm me-2">
                            <button type="button" class="btn" :class="mapType === 'street' ? 'btn-primary' : 'btn-outline-secondary'" @click="updateMapType('street')" title="Street View">
                                <i class="bi bi-map"></i>
                            </button>
                            <button type="button" class="btn" :class="mapType === 'satellite' ? 'btn-primary' : 'btn-outline-secondary'" @click="updateMapType('satellite')" title="Satellite View">
                                <i class="bi bi-globe-americas"></i>
                            </button>
                        </div>
                        
                        <button type="button" class="btn btn-sm btn-outline-primary" @click="getCurrentLocation" :disabled="loadingLocation">
                            <i class="bi bi-geo-alt-fill me-1"></i>
                            {{ loadingLocation ? 'Locating...' : 'My Location' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, watch } from 'vue';

const props = defineProps({
    latitude: {
        type: [Number, String],
        default: null
    },
    longitude: {
        type: [Number, String],
        default: null
    },
    label: {
        type: String,
        default: 'Pick Location from Map'
    },
    hint: {
        type: String,
        default: ''
    }
});

const emit = defineEmits(['update:latitude', 'update:longitude']);

const mapContainer = ref(null);
const loadingLocation = ref(false);
const internalLat = ref(props.latitude);
const internalLng = ref(props.longitude);
const mapType = ref(localStorage.getItem('mapType:v1') || 'street');

let map = null;
let marker = null;
let L = null;
let streetLayer = null;
let satelliteLayer = null;

onMounted(async () => {
    // Dynamic import for Leaflet (SSR safe)
    L = (await import('leaflet')).default;
    await import('leaflet/dist/leaflet.css');

    initMap();
});

onUnmounted(() => {
    if (map) {
        map.remove();
        map = null;
    }
});

// Watch for external prop changes
watch(() => props.latitude, (newVal) => {
    internalLat.value = newVal;
    updateMarkerPosition();
});

watch(() => props.longitude, (newVal) => {
    internalLng.value = newVal;
    updateMarkerPosition();
});

const initMap = () => {
    // Default center (Indonesia/Bali roughly, or props if available)
    const center = [
        props.latitude || -8.670458, // Default Lat (Bali)
        props.longitude || 115.212629 // Default Lng (Bali)
    ];
    
    const zoom = props.latitude && props.longitude ? 15 : 10;

    map = L.map(mapContainer.value).setView(center, zoom);

    streetLayer = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors',
        maxZoom: 19
    });

    satelliteLayer = L.tileLayer('https://mt1.google.com/vt/lyrs=y&x={x}&y={y}&z={z}', {
        attribution: '© Google Satellite',
        maxZoom: 20
    });

    if (mapType.value === 'satellite') {
        satelliteLayer.addTo(map);
    } else {
        streetLayer.addTo(map);
    }

    // Initial Marker if coords exist
    if (props.latitude && props.longitude) {
        addMarker(props.latitude, props.longitude);
    }

    // Map Click Event
    map.on('click', (e) => {
        const { lat, lng } = e.latlng;
        handleLocationSelect(lat, lng);
    });
    
    // Fix map rendering issues in tabs/modals
    setTimeout(() => {
        map.invalidateSize();
    }, 200);
};

const handleLocationSelect = (lat, lng) => {
    internalLat.value = lat;
    internalLng.value = lng;
    
    addMarker(lat, lng);
    
    emit('update:latitude', lat);
    emit('update:longitude', lng);
};

const addMarker = (lat, lng) => {
    if (marker) {
        marker.setLatLng([lat, lng]);
    } else {
        const customIcon = L.divIcon({
            className: 'custom-pin',
            html: '<div style="background-color: #ef4444; width: 15px; height: 15px; border-radius: 50%; border: 2px solid white; box-shadow: 0 2px 4px rgba(0,0,0,0.3);"></div>',
            iconSize: [15, 15],
            iconAnchor: [7, 7]
        });

        marker = L.marker([lat, lng], { icon: customIcon }).addTo(map);
    }
};

const updateMarkerPosition = () => {
    if (!map || !L) return;
    
    if (internalLat.value && internalLng.value) {
        if (marker) {
            marker.setLatLng([internalLat.value, internalLng.value]);
            map.panTo([internalLat.value, internalLng.value]);
        } else {
            addMarker(internalLat.value, internalLng.value);
            map.setView([internalLat.value, internalLng.value], 15);
        }
    }
};

const getCurrentLocation = () => {
    if (!navigator.geolocation) {
        alert("Geolocation is not supported by your browser");
        return;
    }

    loadingLocation.value = true;

    navigator.geolocation.getCurrentPosition(
        (position) => {
            const { latitude, longitude } = position.coords;
            handleLocationSelect(latitude, longitude);
            map.setView([latitude, longitude], 16);
            loadingLocation.value = false;
        },
        (error) => {
            console.error(error);
            alert("Unable to retrieve your location");
            loadingLocation.value = false;
        }
    );
};

const updateMapType = (type) => {
    if (!map) return;
    
    if (type === 'satellite') {
        map.removeLayer(streetLayer);
        satelliteLayer.addTo(map);
    } else {
        map.removeLayer(satelliteLayer);
        streetLayer.addTo(map);
    }
    
    mapType.value = type;
    localStorage.setItem('mapType:v1', type);
};
</script>

<style scoped>
.map-container {
    height: 300px; /* Default height */
    width: 100%;
    z-index: 1;
    background: #f1f5f9;
}

[data-theme="dark"] .map-controls {
    background-color: #1e293b !important;
    border-color: #334155 !important;
    color: #e2e8f0;
}
</style>
