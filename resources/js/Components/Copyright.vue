<template>
    <div class="kitabill-copyright">
        <p class="copyright-text">
            {{ copyrightText }}
        </p>
        <p class="powered-by" v-if="showPoweredBy">
            {{ poweredByText }}
        </p>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { COPYRIGHT } from '../config/kitabill.js';

const props = defineProps({
    showPoweredBy: {
        type: Boolean,
        default: true
    }
});

const page = usePage();
const kitabill = page.props.kitabill || {};

const copyrightText = computed(() => {
    // Gunakan dari props jika ada, fallback ke config
    if (kitabill.copyright?.text) {
        return kitabill.copyright.text;
    }
    return COPYRIGHT.text;
});

const poweredByText = computed(() => {
    if (kitabill.copyright?.poweredBy) {
        return kitabill.copyright.poweredBy;
    }
    return COPYRIGHT.poweredBy;
});
</script>

<style scoped>
.kitabill-copyright {
    text-align: center;
    padding: 1.5rem;
    color: var(--kitabill-text-secondary);
    font-size: 0.875rem;
    border-top: 1px solid var(--kitabill-border);
    background: var(--kitabill-bg-secondary);
    transition: background-color 0.3s ease, border-color 0.3s ease, color 0.3s ease;
}

.copyright-text {
    margin: 0;
    font-weight: 500;
    color: var(--kitabill-text-primary);
}

.powered-by {
    margin: 0.5rem 0 0 0;
    font-size: 0.75rem;
    color: var(--kitabill-text-muted);
}
</style>

