<template>
    <div class="card border-0 shadow-sm mt-3">
        <div class="card-header bg-transparent border-0 py-3 d-flex justify-content-between align-items-center" 
             @click="collapsed = !collapsed" 
             style="cursor: pointer">
            <h6 class="mb-0 fw-bold">
                <i class="bi bi-book me-2"></i>Read Me
            </h6>
            <i class="bi" :class="collapsed ? 'bi-chevron-down' : 'bi-chevron-up'"></i>
        </div>
        <div class="card-body" v-show="!collapsed">
            <div class="small text-muted">
                <slot></slot>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue';

const collapsed = ref(false);
</script>

<style scoped>
.card-header:hover {
    background-color: rgba(0,0,0,0.02) !important;
}
</style>
