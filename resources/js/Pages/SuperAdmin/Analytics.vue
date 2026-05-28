<template>
    <SuperAdminLayout>
        <Head title="Analytics" />
        
        <div class="container-fluid">
            <div class="placeholder-page">
                <i class="bi bi-bar-chart"></i>
                <h3>Analytics</h3>
                <p>Advanced analytics and insights coming soon...</p>
            </div>
        </div>
    </SuperAdminLayout>
</template>

<script setup>
import { Head } from '@inertiajs/vue3';
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue';
</script>

<style scoped>
.placeholder-page {
    text-align: center;
    padding: 100px 20px;
}

.placeholder-page i {
    font-size: 80px;
    color: #8B5CF6;
    margin-bottom: 24px;
}

.placeholder-page h3 {
    font-size: 32px;
    font-weight: 700;
    color: #1E293B;
    margin-bottom: 12px;
}

:global(.dark) .placeholder-page h3 {
    color: #F1F5F9;
}

.placeholder-page p {
    color: #64748B;
    font-size: 16px;
}

:global(.dark) .placeholder-page p {
    color: #94A3B8;
}
</style>



